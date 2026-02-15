<?php

namespace FoF\Releases\Repository;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Post\CommentPost;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

class ReleaseRepository
{
    public function __construct(
        protected ExtensionManager $extensions
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

        return [
            'id' => $post->id,
            'number' => $post->number,
        ];
    }

    protected function formatReleaseContent(?string $changelog, string $tagName, string $releaseUrl, string $author): string
    {
        $content = "## 🚀 New Release: {$tagName}\n\n";

        if ($author !== '') {
            $content .= "**Author:** {$author}\n";
        }
        if ($releaseUrl !== '') {
            $content .= "**Release URL:** {$releaseUrl}\n\n";
        }

        if ($changelog) {
            $content .= "### Changelog\n\n";
            $content .= $changelog;
        }

        return $content;
    }
}
