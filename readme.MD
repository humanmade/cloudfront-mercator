# CloudFont Mercator Integration

When domains are added and removed from a WordPress multisite using the Mercator plugin, this integration will
sync the domain changes to any CloudFront Distribution of your choosing.

## Installation

1. Network Activate this plugin
1. Add the following to your `wp-config.php`:

```php
define( 'CLOUDFRONT_MERCATOR_AWS_REGION', 'us-east-1' );
define( 'CLOUDFRONT_MERCATOR_AWS_KEY', 'your key here' );
define( 'CLOUDFRONT_MERCATOR_AWS_SECRET', 'your secret here' );
define( 'CLOUDFRONT_MERCATOR_DISTRIBUTION_ID', 'your cf distro here' );
```
