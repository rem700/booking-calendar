# Tests

End-to-end tests run against a throwaway local WordPress with this plugin installed. They wipe all requests and clients on that site, so never point them at a real site.

## What you need

- WordPress with this folder linked or copied to `wp-content/plugins/bk-booking` and pretty permalinks on
- A web server for it that can answer several requests at once (the calendar feed test loads a file from the same site), for example `PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080` in the WordPress folder
- Node.js 18 or newer and Playwright with Chromium (`npm i -g playwright && npx playwright install chromium`)
- A must-use plugin that writes e-mails to a file instead of sending them:

```php
<?php // wp-content/mu-plugins/test-mail-log.php
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	file_put_contents( getenv( 'MAIL_LOG' ) ?: '/tmp/mail.log', wp_json_encode( $atts ) . "\n", FILE_APPEND );
	return true;
}, 10, 2 );
```

- For the Instagram tests, copy `tests/e2e/mock-instagram.php` to `wp-content/mu-plugins/`. It answers calls to the Instagram API with test data, so nothing goes to Instagram.

## Running

```sh
WP_URL=http://127.0.0.1:8080 \
WP_PATH=/path/to/wordpress \
MAIL_LOG=/tmp/mail.log \
WP_ADMIN_USER=admin WP_ADMIN_PASS=… \
node --test --test-concurrency=1 tests/e2e/*.test.mjs
```

`tests/e2e/seed.php` resets the plugin settings to a known studio (one service, two halls, open 10–18 every day) and makes a page `/book/` with the form.
