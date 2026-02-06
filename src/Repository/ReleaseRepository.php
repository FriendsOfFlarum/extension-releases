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

    public function createReleasePost(User $user, int $discussionId, ?string $changLog, string $tagName, $releaseUrl, $repositoryName, $author, ServerRequestInterface $request): ResponseInterface
    {
        // Use the Flarum internal API client to create a post in the specified discussion
        $content = $this->formatReleaseContent($changLog, $tagName, $releaseUrl, $repositoryName, $author);

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
            //->withActor($user)
            ->withBody($params)
            ->post('/posts');
    }

    protected function formatReleaseContent(?string $changelog, string $tagName, string $releaseUrl, string $repositoryName, string $author): string
    {
        $content = "## 🚀 New Release: {$tagName}\n\n";
        $content .= "**Repository:** {$repositoryName}\n";
        $content .= "**Author:** {$author}\n";
        $content .= "**Release URL:** {$releaseUrl}\n\n";

        if ($changelog) {
            $content .= "### Changelog\n\n";
            $content .= $changelog;
        }

        return $content;
    }
}
