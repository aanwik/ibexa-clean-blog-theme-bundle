# Ibexa Clean Blog Theme Bundle

A clean, responsive, and fully integrated blog theme for [Ibexa DXP](https://ibexa.co), adapted from the popular [Start Bootstrap Clean Blog](https://startbootstrap.com/theme/clean-blog) template. Developed and maintained by [Aanwik](https://aanwik.com).

## Features

- **Fully Integrated Ibexa Templates:** Provides complete Twig templates for Homepages, Blog Pages, Archives, Author Profiles, Tags, and Categories seamlessly wired into Ibexa's rendering engine.
- **Dynamic Menus & Navigation:** Includes built-in support for dynamically generating header and footer menus using simple "Show in Header" and "Show in Footer" content boolean flags.
- **Rich Author Profiles:** Complete author attribution system linking blog posts to System Users (`post_author`), automatically generating dedicated author bio pages.
- **Automated Demo Content:** Ships with a powerful Symfony Console command (`aanwik:clean-blog:import-demo`) that automatically provisions all necessary Content Types, sets up the folder structure, creates demo users, and generates beautiful sample blog posts and static pages.
- **Search Integration:** Minimalist header search bar natively hooked into Ibexa's Search Service.
- **Responsive Design:** Mobile-first layout emphasizing readability, elegant typography, and stunning hero images.

## Setup Instructions

### 1. Installation

Install the bundle via Composer in your Ibexa DXP project:

```bash
composer require aanwik/ibexa-clean-blog-theme-bundle
```

### 2. Enable the Bundle

Ensure the bundle is registered in your `config/bundles.php` file (Symfony Flex should do this automatically):

```php
return [
    // ...
    Aanwik\IbexaCleanBlogThemeBundle\AanwikIbexaCleanBlogThemeBundle::class => ['all' => true],
];
```

### 3. Clear Cache

Clear the Symfony cache to register the new templates, routes, and services:

```bash
php bin/console cache:clear
```

### 4. Import Demo Content (Recommended)

To quickly set up the Clean Blog structure, generate the required Content Types, and populate your site with interconnected demo content (including Users, Categories, and Posts), run:

```bash
php bin/console aanwik:clean-blog:import-demo
```
*Note: This command will set the root folder (Location ID 2) to use the Clean Blog Homepage layout and create standard frontend folders (Blog, Categories, Tags, Authors, Archives).*

### 5. Assets (Optional/Webpack Encore)

The theme's CSS and JS are included in the bundle's `Resources/public` directory. Depending on your Ibexa frontend configuration, you may need to install assets or compile them via Webpack Encore:

```bash
php bin/console assets:install
```

## Credits

This project stands on the shoulders of giants. We extend our sincere gratitude to:

*   **Start Bootstrap** for creating the beautiful, open-source [Clean Blog](https://startbootstrap.com/theme/clean-blog) HTML/CSS template upon which this visual theme is based.
*   **Ibexa** for building the incredibly powerful and extensible [Ibexa DXP](https://ibexa.co) platform that drives the backend content management and routing.
*   **Aanwik** for developing, adapting, and maintaining this bundle to bridge the gap between Start Bootstrap's frontend and Ibexa's robust backend architecture.

## License

This bundle is open-sourced software licensed under the [Creative Commons Attribution 4.0 International License (CC BY 4.0)](https://creativecommons.org/licenses/by/4.0/). 

**Note on Attribution:** You are free to share and adapt this material, provided you give appropriate credit to Aanwik.

*The original Clean Blog HTML template by Start Bootstrap is licensed under the MIT License.*
