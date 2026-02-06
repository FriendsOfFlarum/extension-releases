<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use FoF\Releases\Repository\ReleaseRepository;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ReceiveWebhookController implements RequestHandlerInterface
{
    public function __construct(
        protected ReleaseRepository $releases
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('fof-releases.publishReleaseUpdates');    
    
        $body = $request->getParsedBody();

        // Validate required fields
        $discussionId = Arr::get($body, 'discussion_id');
        $changelog = Arr::get($body, 'changelog');
        $tagName = Arr::get($body, 'tag_name');
        $releaseUrl = Arr::get($body, 'release_url');
        $repositoryName = Arr::get($body, 'repository_name');
        $author = Arr::get($body, 'author'); // GitHub/GitLab username

        if (!$discussionId || !$changelog || !$tagName) {
            throw new ValidationException([
                'message' => 'Missing required fields: discussion_id, changelog, or tag_name.'
            ]);
        }

        return $this->releases->createReleasePost(
            $actor,
            $discussionId,
            $changelog,
            $tagName,
            $releaseUrl,
            $repositoryName,
            $author,
            $request
        );
    }
}
