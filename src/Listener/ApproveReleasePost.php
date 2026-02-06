<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases\Listener;

use Flarum\Post\Event\Saving;

class ApproveReleasePost
{
    public function handle(Saving $event): void
    {
        $post = $event->post;

        // Only handle new posts (not edits)
        if ($post->exists) {
            return;
        }

        // Check if this post is being created through our webhook
        // We can identify it by checking the request metadata or content pattern
        if ($event->actor->can('fof-releases.publishReleaseUpdates')) {
            // Auto-approve posts created by users with webhook permission
            $post->is_approved = true;
        }
    }
}
