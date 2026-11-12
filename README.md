# Faculty portfolio · comparison version

This is a separate, reusable faculty portfolio built with PHP and React. Sample content ships in `data/content.example.php`; edit it from the admin page. This folder is separate from the main AIC website.

## Launch locally

1. Double-click `start-local.command`, or open Terminal in this folder and run `php -S 127.0.0.1:8001 -t .`.
2. Open [http://127.0.0.1:8001](http://127.0.0.1:8001) for the public portfolio.
3. Open [http://127.0.0.1:8001/admin/](http://127.0.0.1:8001/admin/) for the admin page.
4. When no password has been set, create one from the host computer with at least 12 characters. Later visits require the configured admin email and password. Set the admin email in `data/admin-config.php` (copy `data/admin-config.example.php`).

The admin page edits profile details, section headings, visibility, and order. It lets you add, edit, remove, or link entries in each section, and add custom sections. Portraits, notices, announcements, and publication entries support optional images. Publication categories and Scopus / Impact Factor labels are editable; only enter verified metrics. The saved content is stored in `data/content.php`. Admin sign-in uses an email address and a password hash. The private `data/admin-auth.php` file stores accounts as an email-to-hash map, so more accounts can be added later; that file is excluded from GitHub. Initial password setup is restricted to the host computer and login attempts are rate limited.

The top theme control switches between light and dark mode. React and Google Fonts load from public CDNs, so the browser needs an internet connection for those assets. PHP 8.0 or newer is required to run the site locally.

## Project files

- `index.php` loads the saved portfolio data and starts the public page.
- `assets/site.js` renders the interactive profile, section navigation, publication filters, and theme switch.
- `assets/site.css` contains the portfolio layout and responsive rules; `assets/reference-theme.css` contains the final visual adjustments.
- `admin/index.php` handles sign-in, password changes, portfolio validation, and saving.
- `admin/admin.js` builds the editing controls; `admin/admin.css` styles the admin page.
- `data/content.php` stores the editable profile and sections. `data/admin-auth.php` stores only the password hash.


## Private data (not in git)

`data/content.php` (the real portfolio content), `data/admin-config.php`, `data/admin-auth.php`, `assets/uploads/`, `assets/profile.*` and any portrait are git-ignored. On first run the site shows `data/content.example.php`; the first save from the admin page creates `data/content.php`.
