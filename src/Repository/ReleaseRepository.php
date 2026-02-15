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
    public function createReleasePost(User $user, int $discussionId, ?string $changelog, string $tagName, ?string $releaseUrl, ?string $author, ServerRequestInterface $request): array
    {
        $discussion = Discussion::query()
            ->whereVisibleTo($user)
            ->findOrFail($discussionId);

        $user->assertCan('reply', $discussion);

        $content = $this->formatReleaseContent($changelog, $tagName, $releaseUrl ?? '', $author ?? '');
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

    protected function formatReleaseContent(?string $changelog, string $tagName, string $releaseUrl, string $author): string
    {
        $content = "## 🚀 New Release: {$tagName}\n\n";

        if ($author !== '') {
            $displayAuthor = $this->resolveAuthorMention($author);
            $content .= "**Author:** {$displayAuthor}\n";
        }
        if ($releaseUrl !== '') {
            $content .= "**Release URL:** {$releaseUrl}\n\n";
        }

        if ($changelog) {
            $content .= "### Changelog\n\n";
            $content .= $this->replacePlatformUsernamesInContent($changelog);
        }

        return $content;
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

        // Replace longer usernames first to avoid partial matches (e.g. "im" inside "imorland")
        uksort($mappings, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($mappings as $platform => $forum) {
            $platform = trim($platform);
            $forum = trim($forum);
            if ($platform === '' || $forum === '') {
                continue;
            }
            $mention = $this->formatUserMention($forum);
            if ($mention === null) {
                continue;
            }
            $quoted = preg_quote($platform, '/');
            // Match @username (consume the @) or bare username when not already part of a mention
            $pattern = '/@' . $quoted . '\b|(?<![@\w])' . $quoted . '\b/ui';
            $content = preg_replace($pattern, $mention, $content);
        }

        return $content;
    }

    /**
     * Format a forum username as a Flarum user mention (@"displayname"#id).
     * Returns null if the user is not found.
     */
    protected function formatUserMention(string $forumUsername): ?string
    {
        $user = $this->users->query()->where('username', $forumUsername)->first();
        if (!$user || !isset($user->id, $user->username)) {
            return null;
        }
        $displayName = str_replace(['"', '\\'], ['\\"', '\\\\'], $user->display_name ?? $user->username);

        return '@"' . $displayName . '"#' . $user->id;
    }

    /**
     * @return array<string, string> platform username => forum username
     */
    protected function getUsernameMappings(): array
    {
        $raw = $this->settings->get('fof-releases.username_mappings');
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $mappings = [];
        foreach ($decoded as $key => $mapping) {
            if (is_array($mapping) && isset($mapping['platform'], $mapping['forum'])) {
                $mappings[trim((string) $mapping['platform'])] = trim((string) $mapping['forum']);
            } elseif (is_string($key) && (is_string($mapping) || is_numeric($mapping))) {
                $mappings[trim($key)] = trim((string) $mapping);
            }
        }

        return $mappings;
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

        return $platformUsername;
    }
}
