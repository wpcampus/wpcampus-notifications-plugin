# WPCampus Notifications - WordPress Plugin

WordPress plugin that manages notification functionality for WPCampus websites. Uses the WordPress REST API.

You can view the notifications JSON feed at [https://wpcampus.org/wp-json/wp/v2/notifications](https://wpcampus.org/wp-json/wp/v2/notifications).

## Implementation

The majority of the code that adds the notifications to the WPCampus websites exists in [our network plugin](https://github.com/wpcampus/wpcampus-network-plugin). We use mustache templating to display the notifications. The mustache template for each website exists in its own theme.

### Required Code

* Include the [mustache npm package](https://github.com/wpcampus/wpcampus-network-plugin/blob/master/package.json#L25).
* Make sure the mustache file is [copied to your assets folder](https://github.com/wpcampus/wpcampus-network-plugin/blob/master/gulpfile.js#L38-L39).
* [Create the Javascript file](https://github.com/wpcampus/wpcampus-network-plugin/blob/master/assets/js/wpcampus-notifications.js) to get the notifications and use to populate the mustache template.
  * We keep our script in our network plugin so it's in one place and always available/loaded for each site.
* [Enqueue the notifications script](https://github.com/wpcampus/wpcampus-network-plugin/blob/master/wpcampus-network.php#L222).
  * We also load our script in our network plugin.
* Be sure to add the mustache template. The mustache template for each of our websites exists in its own theme:
  * [WPCampus 2017 theme](https://github.com/wpcampus/wpcampus-2017-theme/blob/master/partials/notifications.html)
  * [WPCampus Online theme](https://github.com/wpcampus/wpcampus-online-theme/blob/master/partials/notifications.html)
  
## Disclaimer

This repo is shared for educational purposes. Feel free to explore, copy, submit fixes, and share the code.

**However, please respect that the WPCampus branding and design are intended solely for the WPCampus organization.**
