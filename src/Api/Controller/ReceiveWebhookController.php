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

        $body = $request->getParsedBody() ?? [];

        $discussionId = Arr::get($body, 'discussion_id');
        $changelog = Arr::get($body, 'changelog');
        $tagName = Arr::get($body, 'tag_name');
        $releaseUrl = Arr::get($body, 'release_url');
        $author = Arr::get($body, 'author');

        if (!$discussionId || !$changelog || !$tagName) {
            throw new ValidationException([
                'message' => 'Missing required fields: discussion_id, changelog, or tag_name.'
            ]);
        }

        $response = $this->releases->createReleasePost(
            $actor,
            (int) $discussionId,
            $changelog,
            $tagName,
            $releaseUrl,
            $author,
            $request
        );

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 201) {
            return $response;
        }

        $body = json_decode($response->getBody()->getContents(), true);
        if (isset($body['data']['id'], $body['data']['attributes']['number'])) {
            return new JsonResponse([
                'success' => true,
                'post_id' => (int) $body['data']['id'],
                'post_number' => (int) $body['data']['attributes']['number'],
            ], 201);
        }

        return $response;
    }
}
