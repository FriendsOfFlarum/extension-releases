<?php

namespace FoF\Releases\Repository;

use Flarum\Api\Client;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ReleaseRepository
{
    public function __construct(
        protected Client $api
    ) {
    }

    public function createReleasePost(User $user, int $discussionId, ?string $changelog, string $tagName, ?string $releaseUrl, ?string $author, ServerRequestInterface $request): ResponseInterface
    {
        $content = $this->formatReleaseContent($changelog, $tagName, $releaseUrl ?? '', $author ?? '');

        $params = [
            'data' => [
                'type' => 'posts',
                'attributes' => [
                    'content' => $content,
                ],
                'relationships' => [
                    'discussion' => [
                        'data' => [
                            'type' => 'discussions',
                            'id' => (string) $discussionId,
                        ],
                    ],
                ],
            ],
        ];

        return $this->api
            ->withParentRequest($request)
            ->withBody($params)
            ->post('/posts');
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
