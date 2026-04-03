=== TryLoom - Virtual Try On for WooCommerce ===
Contributors: ToolTeek, dinethchamuditha
Tags: woocommerce, virtual try-on, product visualization, e-commerce, fashion
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 1.5.1
Requires PHP: 7.2
WC requires at least: 5.0
WC tested up to: 10.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

TryLoom lets customers virtually try on clothing, shoes, hats, and eyewear in WooCommerce.

== Description ==

**TryLoom – Virtual Try On for WooCommerce** by ToolTeek adds powerful virtual try-on functionality to your WooCommerce store.  
Customers can upload their photos and virtually try on products like clothing, shoes, hats, or eyewear directly on product pages.  
This enhances the shopping experience, reduces returns, and boosts conversions.

**Important: SaaS Connection Required**
This plugin acts as a connector to the TryLoom Cloud Platform. All image processing and AI generation are performed securely on our external cloud servers to ensure high-quality results without slowing down your website hosting. A valid API connection (free or paid) is required for the plugin to function.

= Key Features =

* **Cloud-Powered AI:** Offloads complex image processing to our specialized cloud infrastructure.
* **Easy Integration:** Seamlessly adds a “Try On” button to WooCommerce product pages.
* **User Photo Upload:** Customers can upload photos with options to save for future use (configurable).
* **Product Variation Support:** Select and try on different product variations.
* **Secure Image Handling:** Images are stored securely with privacy protections; scheduled deletions.
* **Account Integration:** Users can manage saved photos and view try-on history in their WooCommerce account page.
* **Admin Controls:** Enable/disable features, set generation limits, customize appearance, and monitor usage stats.
* **Customizable:** Theme support (light/dark), primary color, button placement, and custom CSS options.
* **Analytics:** Dashboard widget and stats for try-on usage.

This plugin requires WooCommerce to function.  
It uses a cloud-based API for image processing, ensuring high-quality results without taxing your server.  

For more details, visit the [plugin documentation](https://gettryloom.com/docs)

== Installation ==

1. Upload the `tryloom` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Admin Menu → TryLoom** to configure the plugin.
4. Ensure WooCommerce is installed and active.
5. For full functionality, enter your platform key (a free trial activates automatically).

= Manual Installation =

1. Download the plugin ZIP file.
2. In your WordPress dashboard, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP file and activate.

After activation, the plugin will automatically set up a free trial key if no paid key is provided.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
Yes. TryLoom is an extension for WooCommerce and requires it to be installed and active.

= How do I get a platform key? =
Upon activation, a free trial key is automatically fetched. For unlimited usage, subscribe at [TryLoom Official Website](https://gettryloom.com/).

= Where are user images stored? =
Images are stored securely in a protected directory on your server (`wp-content/uploads/tryloom/`).  
They are not publicly accessible without authentication. and you can desable the ‘Enable Try On History’ option at any time in the dashboard.

= Can I limit try-on generations? =
Yes. Set limits per user in the plugin settings (e.g., 5 generations per day).

= Does it support variable products? =
Yes. Customers can select variations before generating the try-on.

= How do I add the Try On button? =
By default, it’s added after the “Add to Cart” button.  
You can change placement or use the shortcode `[tryloom]`.

= Is there a privacy policy suggestion? =
Yes. The plugin includes suggested text in the admin settings. Add it to your site’s privacy policy.

= What happens to images after use? =
You can configure image retention: delete after a set period (default 30 days) or keep if saved by user.

= Can I customize the popup? =
Yes. Theme color, primary color, and custom CSS options are available in settings.

== Screenshots ==

1. **Product Page with Try-On Button** – “Try On” button on a WooCommerce product page.  
2. **Try-On Popup** – User uploads photo and selects variation.  
3. **Generated Result** – Virtual try-on image with download and retry options.  
4. **Account Tab** – User manages saved photos and history.  
5. **Admin Settings** – Configuration options in the WordPress dashboard.  
6. **Dashboard Widget** – Usage statistics overview.

== Changelog ==

= 1.5.2 = 

* Fix: Added missing html close tag

= 1.5.1 =

* Security: Patched a critical Server-Side Request Forgery (SSRF) vulnerability in the image fetch API to prevent internal network scanning.
* Security: Secured the My Account and Try On popup upload endpoints against Cross-Site Request Forgery (CSRF) and restricted file types to prevent unauthorized script uploads.
* Security: Implemented a strict transient locking mechanism to fix a race condition that allowed users to bypass generation quotas.
* Security: Hardened admin dashboard notices and frontend UI rendering to prevent potential Cross-Site Scripting (XSS).
* Fix: Overhauled the plugin uninstaller to properly sweep orphaned transient data and safely delete media attachments without leaving broken "ghost" images in the WordPress library.
* Fix: Resolved a storage leak where guest users uploading photos but abandoning the generation process would leave unrecorded files on the server.
* Fix: Added safe fallback logic to eliminate a PHP undefined variable notice when processing simple, non-variable WooCommerce products.
* Fix: Implemented a safe loopback check to prevent the plugin from executing slow HTTP requests when attempting to load local staging environment files.

= 1.5.0 =

* New: Integrated Cloudflare Turnstile for bot protection and spam prevention.
* New: Added dynamic Privacy Policy note suggestions tailored to your specific image storage configurations.
* Enhancement: Completely reorganized the admin settings menu into a clean, tabbed interface for a significantly better user experience.
* Enhancement: Rolled out various frontend UI improvements for a smoother and more polished look.
* Security: Hardened role-based access restrictions to improve overall site security.

= 1.4.0 =

* Performance: Overhauled the generation limit engine to use lightweight user metadata instead of heavy database queries, making load times lightning fast for high-volume stores.
* Performance: Completely removed heavy Font Awesome dependencies. All UI icons are now ultra-lightweight, zero-dependency inline SVGs to significantly boost PageSpeed scores.
* Performance: Rebuilt the variation caching engine to prevent wp_options database bloat and strictly isolate price caching, eliminating potential wholesale pricing leaks.
* New: Introduced a Role-Based Limitations engine. Administrators can now assign custom generation limits to specific user roles (e.g., VIP, Wholesale).
* New: Added an admin toggle to completely hide the variation selector for streamlined, zero-query Try-On experiences.
* Improvement: Added a smart UI filter that visually deduplicates product variations sharing the exact same thumbnail image.
* Update: Completely refactored the frontend popup utilizing strict BEM CSS architecture and CSS Grid, ensuring perfectly smooth crossfade transitions and zero conflicts with aggressive WooCommerce themes.
* Update: Modernized the admin dashboard statistics layout with a custom, responsive grid architecture for a cleaner backend aesthetic.
* Security: Closed a loophole that allowed users to bypass generation limits by disabling the history feature.
* Fix: Calibrated generation limit resets to mathematically synchronize with the specific WordPress local timezone settings rather than strict UTC.
* Fix: Integrated directly with WooCommerce native stock hooks to automatically clear variation caches when inventory runs out, preventing "ghost" variations.
* Fix: Resolved a JavaScript race condition that caused duplicate variations to load when rapidly opening and closing the popup.
* Fix: Cleaned up PHP background warnings during file deletion processes and purged deprecated hook registrations.

= 1.3.0 =
* Performance: Implemented Transient Caching for product variations. The "Try On" button is now instant.
* Performance: Added Database Indexes to history tables. Dashboard statistics load significantly faster.
* Performance: Optimized "Autoload" options to reduce database bloat on every page load.
* Stability: Fixed "Memory Exhausted" and "Time Limit" crashes on shared hosting environments.
* Stability: Implemented Batch Processing for cron cleanup tasks to prevent server timeouts.
* Security: Added self-healing directory protection that adapts to server environment (Apache/Nginx).
* Fix: Reduced database queries on product pages from 4 to 1 per load.

= 1.2.5 =
* Fix: Critical fix for theme compatibility - Added pointer-events: none to hidden popup container to prevent blocking clicks on theme elements (cart drawer, login modal, search popup, etc.) when plugin is active but popup is not open.
* Fix: Resolves "page freeze" issue where users couldn't click navigation icons after installing the plugin.

= 1.2.4 =
* Fix: Resolved a critical "Scroll Lock" conflict that caused the page interface to freeze on certain themes (e.g., Minimog) when interacting with Cart or Search drawers.
* Update: Replaced aggressive inline-style scroll locking with a passive "Class-Based" system (tryloom-scroll-lock) to prevent interference with theme navigation.
* Fix: Corrected a Z-Index layering issue where the plugin container could block clicks on underlying buttons even when closed.
* Improvement: Added a "Safety Net" script to force-release any scroll locks immediately upon page load.
* Improvement: Optimized CSS pointer-events to ensure the plugin is completely "transparent" to mouse clicks when not in use.

= 1.2.2 =
* Fix: Optimized asset loading logic to prevent script execution on non-product pages.
* Update: Refactored wp_footer hooks with strict conditional checks for enhanced theme compatibility.
* Fix: Resolved a JavaScript execution conflict that affected navigation menus on certain premium themes.
* Improvement: Added defensive null-checks to the core initialization script to prevent global execution errors.

= 1.2.1 =
* Update: Migrated to new backend infrastructure for service continuity.
* Fix: Updated all API endpoints to match new configuration.

= 1.2.0 =
* New: Implemented cloud status check endpoint for reliable usage tracking.
* Update: Enhanced SSL verification for all API calls.
* Fix: Added strict directory traversal protection for file handling.
* Update: Optimized database queries for the dashboard and settings page.
* Update: Improved image serving with binary streaming to reduce server memory usage.
* Update: Switched to local hosting for Font Awesome icons (GDPR compliance).
* Update: Refreshed "My Virtual Closet" pagination and layout.

= 1.1.0 =
* NEW: Added "Generation Mode" selector (Try-On, Studio, Auto).
* NEW: "Studio Mode" now regenerates the background and lighting for professional results.
* NEW: "Auto Mode" intelligently detects if the uploaded photo needs full studio processing.
* UPDATE: Migrated API endpoint to US-Central1 for 3x faster generation speeds.
* FIX: Critical update for backend connectivity.

= 1.0.5 =
* Fix: Updated external service documentation to match specific API domain.
* Fix: Implemented late escaping for inline styles.
* Fix: Replaced echo with readfile for binary image output.

= 1.0.4 =
* Security: Added nonce verification for GET requests (pagination and image protection).
* Security: Fixed file path resolution to use wp_upload_dir() for all server setups.
* Security: Added validation for local file paths before using WP_Filesystem.
* Security: Sanitized custom CSS before adding inline styles.
* Fix: Improved binary image data output with proper documentation.

= 1.0.3 =
* Security: Added nonce verification to all AJAX handlers and REST API endpoints.
* Security: Implemented WP_Filesystem for safe file operations and remote requests.
* Fix: Updated filter prefixes to avoid namespace conflicts.
* Fix: Refactored file path resolution logic using WordPress standards.
* Improved: Added detailed documentation for external API services.

= 1.0.2 =
* Fixed image loading issues in popup after generation.
* Improved error handling and image fallback mechanisms.
* Removed guest user option from allowed user roles.

= 1.0.1 =
* Fixed minor bug in try-on popup.
* Improved image handling.

= 1.0.0 =
* Initial release: Core virtual try-on functionality, admin settings, user account integration, and API support.

== Upgrade Notice ==

=1.5.2=
Added missing html close tag

= 1.5.1 =
Critical Security Update: Patches multiple high-severity vulnerabilities including unauthorized file uploads, server-side request forgery (SSRF), and usage quota race conditions. Also includes major improvements to database cleanup during uninstallation. Immediate update is highly recommended to ensure store security and optimal server performance.

= 1.5.0 =
Feature & Security Update: Version 1.5.0 introduces Cloudflare Turnstile for advanced bot protection and hardens role-based security access. We have also reorganized the admin settings into an easy-to-use tabbed interface. We recommend reviewing your settings after updating to configure the new Turnstile bot protection.

= 1.4.0 =
Major Architecture & Performance Update: This release drastically improves plugin speed by removing heavy CSS libraries and overhauling the database query engine. It introduces a bulletproof BEM CSS architecture to prevent theme styling conflicts, adds a new revenue-driving Upsell screen for users who hit their limits, and enables custom generation limits by user role. Highly recommended for all users to maximize site speed and UI stability.

= 1.3.0 =
Major Performance Update: Includes critical database indexing, caching, and stability fixes. Highly recommended for all users to improve site speed and prevent server timeouts.

= 1.2.5 =
Critical Update: Fixes an issue where the hidden plugin popup could block clicks on theme elements like Cart, Login, and Search icons. Recommended for all users.

= 1.2.4 =
Critical Update: Fixes a major conflict where the website scroll or buttons (Cart/Search) could freeze on Product Pages. Recommended for all users immediately.

-= 1.2.2 = 
Maintenance Update: Fixes a theme compatibility conflict involving navigation menus and optimizes site-wide performance by strictly isolating plugin assets to WooCommerce pages. Highly recommended for users of Minimog and other Elementor-based themes.


= 1.2.1 =
Critical Maintenance: Updates API endpoints for the new backend provider. Required for continued service operation.

= 1.2.0 =
Major update: Includes critical security hardening (SSL & file handling), GDPR compliance fixes (local fonts), and significant performance improvements. Recommended for all users.

= 1.1.0 =
Major Update: Introduces new Generation Modes (Studio/Auto), 3x faster speeds, and critical backend fixes.

= 1.0.5 =
Bug fixes: Updated documentation, improved security with late escaping, and better binary image handling.

= 1.0.4 =
Security enhancements: Nonce verification for GET requests, improved file path handling, and CSS sanitization.

= 1.0.3 =
Security improvements, filesystem updates, and code compliance fixes.

= 1.0.2 =
Fixed image loading issues in popup. Improved error handling.

= 1.0.1 =
Minor bug fixes. No action required.

= 1.0.0 =
Initial release.

== Arbitrary Section ==

= Requirements =
* WordPress 5.6 or higher  
* PHP 7.2 or higher  
* WooCommerce 5.0 or higher  
* Active internet connection for API calls (image generation)

== Support ==

For support, visit [TryLoom Support](https://gettryloom.com/support) or open an issue on the WordPress plugin forum.

== Privacy ==

This plugin handles user-uploaded images with care.  
Images are processed via a secure API and stored only as configured.  
Ensure your site’s privacy policy covers image uploads and processing.  
The plugin does **not share data with third parties** beyond the API used for generation.

== External Services (API) ==

This plugin relies on the **TryLoom Cloud Platform** (maintained by ToolTeek) to function.
Because AI image generation requires significant computational resources, it cannot run directly on your WordPress hosting environment. Instead, user images and product data are sent to our secure cloud infrastructure for processing and the result is returned to your site.

* **Service:** TryLoom Cloud API & Status Check
* **Hosts:** * `fashiontryon-vqmfnpmz4q-uc.a.run.app` (Image Generation)
    * `status-vqmfnpmz4q-uc.a.run.app` (Service Status & Usage)
* **Used For:** Authenticating the API connection, checking plan usage/status, and performing AI image generation.
* **Data Sent:** Site URL, API credentials, product ID, product image, and the user's uploaded photo.
* **Privacy Policy:** https://gettryloom.com/privacy-policy/
* **Terms and Conditions:** https://gettryloom.com/terms-and-conditions/

== Credits ==

Developed by **ToolTeek**.  
Powered by the **TryLoom API**.
Icons provided by **Lucide** (https://lucide.dev) under the ISC License. 