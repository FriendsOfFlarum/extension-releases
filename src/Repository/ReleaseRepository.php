<?php

namespace FoF\Releases\Repository;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Post\CommentPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Http\Message\ServerRequestInterface;

class ReleaseRepository
{
    /** @var array<string, string>|null */
    protected ?array $mappingsCache = null;

    /** @var array<string, string> forum username => mention string */
    protected array $mentionCache = [];

    public function __construct(
        protected ExtensionManager $extensions,
        protected SettingsRepositoryInterface $settings,
        protected UserRepository $users,
        protected Dispatcher $events
    ) {
    }

    /**
     * Create a release post directly (bypassing the API) so that flarum/approval's
     * UnapproveNewContent listener never runs—it only listens to the Saving event
     * dispatched by the JSON:API PostResource, not by direct model saves.
     *
     * @return array{id: int, number: int}
     */
    public function createReleasePost(User $user, int $discussionId, ?string $changelog, string $tagName, ?string $releaseUrl, ?string $author, ?string $flarumVersion, ServerRequestInterface $request): array
    {
        $discussion = Discussion::query()
            ->whereVisibleTo($user)
            ->findOrFail($discussionId);

        $user->assertCan('reply', $discussion);

        $content = $this->formatReleaseContent($changelog, $tagName, $releaseUrl ?? '', $author ?? '', $flarumVersion ?? '');
        $ipAddress = $request->getAttribute('ipAddress') ?? '127.0.0.1';

        $post = new CommentPost();
        $post->discussion_id = $discussion->id;
        $post->user_id = $user->id;
        $post->ip_address = $ipAddress;
        $post->created_at = Carbon::now();
        $post->setContentAttribute($content, $user);

        if ($this->extensions->isEnabled('flarum-approval')) {
            $post->is_approved = true;
        }

        $post->save();

        // Dispatch Posted so flarum/mentions syncs post_mentions_user (mentions metadata).
        // Direct save bypasses the API, so events are never released automatically.
        foreach ($post->releaseEvents() as $event) {
            if (property_exists($event, 'actor') && ! $event->actor) {
                $event->actor = $user;
            }
            $this->events->dispatch($event);
        }

        return [
            'id' => $post->id,
            'number' => $post->number,
        ];
    }

    protected function formatReleaseContent(?string $changelog, string $tagName, string $releaseUrl, string $author, string $flarumVersion = ''): string
    {
        $content = "## 🚀 New Release: {$tagName}\n\n";
        if ($flarumVersion !== '') {
            $content .= "_" . $this->resolveFlarumCompatibilityLabel($flarumVersion) . "_\n\n";
        }

        if ($author !== '') {
            $displayAuthor = $this->resolveAuthorMention($author);
            $content .= "**Released by:** {$displayAuthor}\n";
        }

        if ($releaseUrl !== '') {
            $content .= "**Release URL:** {$releaseUrl}\n";
        }

        if ($changelog) {
            $content .= "\n----\n\n";
            $content .= $this->replacePlatformUsernamesInContent($this->cleanChangelog($changelog));
        }

        return $content;
    }

    protected function resolveFlarumCompatibilityLabel(string $constraint): string
    {
        // Extract all version numbers from the constraint string
        preg_match_all('/\d+\.\d+/', $constraint, $matches);

        if (empty($matches[0])) {
            return $constraint;
        }

        $majors = array_unique(array_map(fn (string $v) => (int) explode('.', $v)[0], $matches[0]));

        if (count($majors) === 1) {
            return "Targets Flarum " . reset($majors) . ".x";
        }

        // Multiple distinct major versions — show the raw constraint
        return $constraint;
    }

    protected function cleanChangelog(string $changelog): string
    {
        // Remove GitHub's "## What's Changed" heading
        $changelog = preg_replace('/^##\s+What\'s Changed\s*\n/im', '', $changelog);

        // Collapse runs of 3+ blank lines down to 2
        $changelog = preg_replace('/\n{3,}/', "\n\n", $changelog);

        return trim($changelog) . "\n";
    }

    /**
     * Replace platform usernames in content with forum mentions.
     * Uses Flarum's @"displayname"#id format so mentions resolve correctly.
     */
    protected function replacePlatformUsernamesInContent(string $content): string
    {
        $mappings = $this->getUsernameMappings();
        if (empty($mappings)) {
            return $content;
        }

        // Pre-fetch all mapped forum usernames in a single query
        $this->primeMentionCache(array_values($mappings));

        // Replace longer usernames first to avoid partial matches (e.g. "im" inside "imorland")
        uksort($mappings, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($mappings as $platform => $forum) {
            $platform = trim($platform);
            $forum = trim($forum);
            if ($platform === '' || $forum === '') {
                continue;
            }
            $mention = $this->mentionCache[$forum] ?? null;
            if ($mention === null) {
                continue;
            }
            $quoted = preg_quote($platform, '/');
            // Match @username (consume the @) or bare username when not already part of a mention or URL path
            $pattern = '/@' . $quoted . '\b|(?<![@\w\/])' . $quoted . '\b/ui';
            $content = preg_replace($pattern, $mention, $content);
        }

        return $content;
    }

    /**
     * Load mentions for a list of forum usernames in one query and populate the cache.
     *
     * @param string[] $forumUsernames
     */
    protected function primeMentionCache(array $forumUsernames): void
    {
        $forumUsernames = array_unique(array_filter(array_map('trim', $forumUsernames)));
        $uncached = array_diff($forumUsernames, array_keys($this->mentionCache));

        if (empty($uncached)) {
            return;
        }

        $users = $this->users->query()->whereIn('username', $uncached)->get();

        foreach ($users as $user) {
            $displayName = str_replace(['"', '\\'], ['\\"', '\\\\'], $user->display_name ?? $user->username);
            $this->mentionCache[$user->username] = '@"' . $displayName . '"#' . $user->id;
        }

        // Mark not-found usernames as null so we don't query again
        foreach ($uncached as $username) {
            if (!isset($this->mentionCache[$username])) {
                $this->mentionCache[$username] = null;
            }
        }
    }

    /**
     * Format a forum username as a Flarum user mention (@"displayname"#id).
     * Returns null if the user is not found.
     */
    protected function formatUserMention(string $forumUsername): ?string
    {
        if (!array_key_exists($forumUsername, $this->mentionCache)) {
            $this->primeMentionCache([$forumUsername]);
        }

        return $this->mentionCache[$forumUsername] ?? null;
    }

    /**
     * @return array<string, string> platform username => forum username
     */
    protected function getUsernameMappings(): array
    {
        if ($this->mappingsCache !== null) {
            return $this->mappingsCache;
        }

        $raw = $this->settings->get('fof-releases.username_mappings');
        if (!$raw) {
            return $this->mappingsCache = [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->mappingsCache = [];
        }

        $mappings = [];
        foreach ($decoded as $key => $mapping) {
            if (is_array($mapping) && isset($mapping['platform'], $mapping['forum'])) {
                $mappings[trim((string) $mapping['platform'])] = trim((string) $mapping['forum']);
            } elseif (is_string($key) && (is_string($mapping) || is_numeric($mapping))) {
                $mappings[trim($key)] = trim((string) $mapping);
            }
        }

        return $this->mappingsCache = $mappings;
    }

    /**
     * Resolve the author to a forum mention if a mapping exists.
     * Uses Flarum's @"displayname"#id format so mentions resolve correctly.
     */
    protected function resolveAuthorMention(string $platformUsername): string
    {
        $mappings = $this->getUsernameMappings();
        $platformLower = strtolower(trim($platformUsername));

        foreach ($mappings as $platform => $forum) {
            if (strtolower($platform) === $platformLower) {
                $mention = $this->formatUserMention(trim($forum));
                if ($mention !== null) {
                    return $mention;
                }
                return '@' . trim($forum);
            }
        }

        // No mapping found — try a direct lookup by the platform username itself.
        $mention = $this->formatUserMention($platformUsername);

        return $mention ?? $platformUsername;
    }
}
