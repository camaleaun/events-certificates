---
title: "Restricting export and import"
description: "For network administrators: switch off what carries files and drawings in and out of the plugin, with no screen in the settings."
---

Export and Import can take fonts, pictures and vector drawings (SVG paths) from one site to another. On a network (WordPress multisite) you may want to restrict that for security. There is **no screen in the settings** for this: it is a constant, an option and filters, for the person who administers the network.

## The features

| Feature | What it allows |
|---|---|
| `export` | The Export screen and download. |
| `import` | The Import wizard. |
| `zip` | ZIP packages (making and reading them), which carry the fonts and the pictures. |
| `fonts` | Taking fonts in a package and installing them (needs `zip`). |
| `pictures` | Taking Media Library pictures in a package and making them (needs `zip`). |
| `vectors` | The vector drawings (SVG paths) inside a package. |

On a **single site** everything is allowed. On a **network** `zip`, `fonts`, `pictures` and `vectors` are allowed to **super administrators** only; `export` and `import` stay on.

## How to set them

In `wp-config.php` or a must-use plugin:

```php
define( 'EVCERT_RESTRICTIONS', array( 'zip' => false, 'vectors' => false ) );
```

With WP-CLI (an option, saved in the database):

```bash
wp option update evcert_restrictions '{"zip":false,"vectors":false}' --format=json
```

Or with filters, which have the last word (the first one takes the whole list, the second one a single feature):

```php
add_filter( 'evcert_restrictions', function ( $features ) {
	$features['pictures'] = false;
	return $features;
} );

add_filter( 'evcert_allow_vectors', function ( $allowed, $user_id ) {
	return user_can( $user_id, 'manage_network' );
}, 10, 2 );
```

Order: the defaults, the option, the constant, then the filters.

## What a restricted site does

- **Export** is only a JSON file, with **no** vector drawings and **no** pictures: the places stay (their position, size and name) and are empty.
- **Import** leaves out what the file has of a feature that is off, and says so in the log. A ZIP is refused where `zip` is off. A template comes in with **empty places** (a dashed rectangle with its name) so the person can draw the SVG again or choose the picture by hand.
- The site's own signature is not cleared by an import that leaves the signature out.
- With `export` or `import` off, the buttons and the screens are gone, and the requests answer **403**.
