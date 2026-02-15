# Testing Guide

This document provides instructions for testing the FoF Extension Releases extension.

## Manual Webhook Testing

You can test the webhook endpoint manually using curl:

```bash
curl -X POST https://your-flarum-site.com/api/fof/releases/webhook \
  -H "Content-Type: application/json" \
  -H "Authorization: Token YOUR_FLARUM_API_TOKEN" \
  -d '{
    "discussion_id": 1,
    "changelog": "## What'\''s Changed\n\n- Fixed critical bug in authentication\n- Added support for dark mode\n- Improved performance by 50%\n\n**Full Changelog**: https://github.com/owner/repo/compare/v1.0.0...v1.1.0",
    "tag_name": "v1.1.0",
    "release_url": "https://github.com/owner/repo/releases/tag/v1.1.0",
    "author": "octocat"
  }'
```

### Expected Response

**Success (201 Created):**
```json
{
  "success": true,
  "post_id": 123,
  "post_number": 5
}
```

**Error (422 Unprocessable Entity):** Missing required fields (discussion_id, changelog, or tag_name)

**Error (403 Forbidden):** Not authenticated or lacks permission

## Running Automated Tests

### Prerequisites

1. Install dependencies:
   ```bash
   composer install
   ```

2. Setup test database (one-time setup):
   ```bash
   composer test:setup
   ```

### Running Tests

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration
```

### Test Coverage

The extension includes comprehensive test coverage:

#### Unit Tests
- `ReleaseRepositoryTest.php`:
  - Post content formatting with all fields
  - Post content formatting with optional fields omitted

#### Integration Tests
- `ReceiveWebhookTest.php`:
  - Webhook endpoint availability
  - Request validation
  - Authentication verification
  - Permission checks
  - Post creation
  - Content verification

## Testing GitHub Actions Locally

You can test the GitHub Actions workflow locally using [act](https://github.com/nektos/act):

1. Install act:
   ```bash
   # macOS
   brew install act

   # Linux
   curl https://raw.githubusercontent.com/nektos/act/master/install.sh | sudo bash
   ```

2. Create a `.secrets` file:
   ```
   FLARUM_API_TOKEN=your-token-here
   FLARUM_DISCUSSION_ID=1
   ```

3. Run the workflow:
   ```bash
   act release --secret-file .secrets
   ```

## Testing GitLab CI Locally

You can test GitLab CI locally using [gitlab-runner](https://docs.gitlab.com/runner/):

1. Install gitlab-runner:
   ```bash
   # macOS
   brew install gitlab-runner

   # Linux
   sudo curl -L --output /usr/local/bin/gitlab-runner https://gitlab-runner-downloads.s3.amazonaws.com/latest/binaries/gitlab-runner-linux-amd64
   sudo chmod +x /usr/local/bin/gitlab-runner
   ```

2. Run the pipeline:
   ```bash
   gitlab-runner exec docker notify-flarum-on-release \
     --env FLARUM_API_TOKEN=your-token \
     --env FLARUM_DISCUSSION_ID=1 \
     --env FLARUM_SITE_URL=https://your-site.com
   ```

## Common Issues

### "Invalid API token" error
- Verify the token hasn't expired
- Check that you're using the correct token
- Ensure the user account is still active

### "Discussion not found" error
- Verify the discussion ID exists
- Check that the discussion hasn't been deleted
- Ensure you're using the numeric ID, not the slug

### "User does not have permission" error
- Check the user has permission to post in the discussion
- Verify the discussion isn't locked
- Ensure the user isn't suspended

### Posts not appearing
- Check Flarum's post moderation queue
- Verify the discussion visibility settings
- Check if the extension is enabled in admin panel

## Debugging

Enable debug mode in Flarum to see detailed error messages:

```php
// config.php
'debug' => true,
```

Check Flarum logs:
```bash
tail -f storage/logs/flarum.log
```

Check webhook response headers:
```bash
curl -v -X POST https://your-flarum-site.com/api/fof/releases/webhook \
  -H "Content-Type: application/json" \
  -H "Authorization: Token YOUR_TOKEN" \
  -d '{"discussion_id":1,"changelog":"test","tag_name":"v1.0.0"}'
```
