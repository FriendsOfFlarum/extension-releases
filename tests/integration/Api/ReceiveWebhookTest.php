<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases\Tests\Integration\Api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Http\AccessToken;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

class ReceiveWebhookTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-releases');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test Discussion', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'last_posted_at' => Carbon::now(), 'last_posted_user_id' => 2, 'last_post_number' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post content</p></t>'],
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'fof-releases.publishReleaseUpdates'], // Members group (user 2)
            ],
        ]);
    }

    /**
     * @test
     */
    public function webhook_endpoint_exists(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [],
            ])
        );

        // Should not be 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function webhook_returns_422_when_missing_required_fields(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => 'some-token',
                    // Missing discussion_id, changelog, tag_name
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertStringContainsString('Missing required fields', $body['error']);
    }

    /**
     * @test
     */
    public function webhook_returns_401_with_invalid_token(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => 'invalid-token',
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        $this->assertEquals(401, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Invalid API token', $body['error']);
    }

    /**
     * @test
     */
    public function webhook_returns_404_when_discussion_not_found(): void
    {
        // Create a valid access token for user 2
        $token = AccessToken::generate(2);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => $token->token,
                    'discussion_id' => 999, // Non-existent discussion
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        $this->assertEquals(404, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('Discussion not found', $body['error']);
    }

    /**
     * @test
     */
    public function webhook_creates_post_with_valid_data(): void
    {
        // Create a valid access token for user 2
        $token = AccessToken::generate(2);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => $token->token,
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog content',
                    'tag_name' => 'v1.0.0',
                    'release_url' => 'https://github.com/test/repo/releases/tag/v1.0.0',
                    'repository_name' => 'test/repo',
                    'author' => 'testuser',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('post_id', $body);
        $this->assertArrayHasKey('post_number', $body);

        // Verify post was created in database
        $this->assertNotNull(\Flarum\Post\Post::find($body['post_id']));
    }

    /**
     * @test
     */
    public function webhook_returns_403_when_user_lacks_publish_permission(): void
    {
        // Remove the publish permission
        $this->database()->table('group_permission')
            ->where('permission', 'fof-releases.publishReleaseUpdates')
            ->delete();

        $token = AccessToken::generate(2);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => $token->token,
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertStringContainsString('publish release updates', $body['error']);
    }

    /**
     * @test
     */
    public function webhook_returns_403_when_user_cannot_reply(): void
    {
        // Create a discussion that normal users can't reply to
        // First, we need to remove the reply permission from the discussion
        // For this test, we'll use a suspended user

        $user = User::find(2);
        $user->suspended_until = Carbon::now()->addDay();
        $user->save();

        $token = AccessToken::generate(2);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => $token->token,
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertStringContainsString('permission', $body['error']);
    }

    /**
     * @test
     */
    public function webhook_post_contains_expected_content(): void
    {
        $token = AccessToken::generate(2);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'api_token' => $token->token,
                    'discussion_id' => 1,
                    'changelog' => '- Fixed bug #123\n- Added new feature',
                    'tag_name' => 'v2.5.0',
                    'release_url' => 'https://github.com/fof/example/releases/tag/v2.5.0',
                    'repository_name' => 'fof/example',
                    'author' => 'johndoe',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $post = \Flarum\Post\Post::find($body['post_id']);

        $this->assertStringContainsString('v2.5.0', $post->content);
        $this->assertStringContainsString('Fixed bug #123', $post->content);
        $this->assertStringContainsString('Added new feature', $post->content);
    }
}
