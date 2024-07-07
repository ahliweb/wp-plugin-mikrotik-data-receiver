# MikroTik Data Receiver Plugin

## Overview

The MikroTik Data Receiver plugin for WordPress allows you to receive data from MikroTik routers via REST API and store it in the WordPress database. This data can be viewed and managed from the WordPress admin panel.

## Features

- **Receive Data from MikroTik**: Captures and stores data from MikroTik routers, including IP, MAC, upload/download stats, and more.
- **Admin Panel**: View and manage the received data from a dedicated admin page in WordPress.
- **Token Authentication**: Secure the data transmission using token-based authentication.
- **Data Recapitulation**: View summaries of the data for different time periods (last day, week, month, year, and total).
- **Customizable Token**: Easily update the authentication token via the admin panel.

## Installation

1. Download the plugin files and upload them to your `/wp-content/plugins/` directory or install the plugin directly through the WordPress plugin screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. The plugin will automatically create the necessary database tables and set an initial token.

## Usage

### MikroTik Configuration

To send data from your MikroTik router to WordPress, use the following script:

```plaintext
/ip firewall filter
add chain=forward action=log log-prefix="new-connection:" log=yes

/system script
add name=sendData policy=read,write source="
:local logMessages [/log find where message~\"new-connection:\"]
:foreach i in=\$logMessages do={
    :local message [/log get \$i message]
    :local time [/log get \$i time]

    :if ([:find \$message \"new-connection:\"] != -1) do={
        :local details [:pick \$message 17 [:len \$message]]
        :local ip [:pick \$details 0 [:find \$details \" \" -1]]
        :local mac [:pick \$details ([:find \$details \" \" -1] + 1) [:len \$details]]

        # Dummy data for demonstration, replace with actual values if available
        :local username \"example_username\"
        :local upload \"1024\"
        :local download \"2048\"
        :local duration \"3600\"
        :local interface \"ether1\"
        :local status \"active\"

        /tool fetch url=\"https://yourdomain.com/wp-json/mikrotik/v1/data\" \
        http-method=post \
        http-data=\"username=\$username&ip=\$ip&mac=\$mac&upload=\$upload&download=\$download&duration=\$duration&interface=\$interface&status=\$status&token=your-token\"
    }
    /log remove \$i
}
"

/system scheduler
add name=scheduleSendData interval=10m on-event=sendData
```

### Removing the MikroTik Script

To remove the previously added script from your MikroTik router, use the following commands:

```plaintext
# Remove firewall rule
/ip firewall filter
remove [find log-prefix="new-connection:"]

# Remove script
/system script
remove [find name="sendData"]

# Remove scheduler
/system scheduler
remove [find name="scheduleSendData"]
```

### Admin Panel

1. Navigate to the 'MikroTik Data' page under the WordPress admin menu to view the received data.
2. To update the token, use the 'Update Token' form on the same page. Ensure to input the new token and save changes.

### Shortcode

Use the `[mikrotik_data]` shortcode to display data in posts or pages. The data will be displayed with pagination support.

## Security

- **Token-Based Authentication**: Ensure secure data transmission by validating each request with a token.
- **Nonce Verification**: The admin panel operations are secured using nonce verification to prevent CSRF attacks.

## Database

The plugin creates two tables:
- `wp_mikrotik_data`: Stores the data received from MikroTik.
- `wp_mikrotik_token`: Stores the token used for authentication.

## Changelog

### 1.1
- Added feature to update the token from the admin panel.
- Improved security with prepared statements and output escaping.
- Added caching to reduce direct database queries.

### 1.0
- Initial release.

## License

This plugin is licensed under the MIT License. See the [LICENSE](./LICENSE) file for more information.

## Support

For support and inquiries, please visit [ahliweb.co.id](https://ahliweb.co.id).

```

This `README.md` file provides a comprehensive guide on how to use the MikroTik Data Receiver plugin, including installation, configuration, security features, and instructions for removing the MikroTik script. It also outlines the plugin's functionalities and how to manage and view data within the WordPress admin panel.
