<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases;

use Flarum\Extend;
use Flarum\Post\Event\Saving;
use FoF\Releases\Api\Controller\ReceiveWebhookController;
use FoF\Releases\Listener\ApproveReleasePost;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    // Register API webhook endpoint
    (new Extend\Routes('api'))
        ->post('/fof/releases/webhook', 'releases.webhook', ReceiveWebhookController::class),

    // Auto-approve posts created by webhook users (only when flarum/approval is enabled)
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-approval', fn () => [
            (new Extend\Event())
                ->listen(Saving::class, ApproveReleasePost::class),
        ]),
];
