# Newsman Module for PrestaShop 8 - Configuration Guide

This guide walks you through every setting in the Newsman module for PrestaShop 8 so you can connect your store to your Newsman account and start collecting subscribers, sending newsletters, and tracking customer behavior.

---

## Where to Find the Module Settings

After installing the module, go to **Admin > Modules > Module Manager**, find **Newsman** in the list and click **Configure**. All settings are on a single configuration page organized into sections.

---

## Getting Started - Connecting to Newsman

Before you can use any feature, you need to connect the module to your Newsman account. There are two ways to do this:

### Option A: Quick Setup with OAuth (Recommended)

1. Go to **Admin > Modules > Newsman > Configure**.
2. Click the **Connect with Newsman** button (or **Reconfigure** if you have already configured the module before).
3. You will be taken to the Newsman website. Log in if needed and grant access.
4. You will be redirected back to a page in PrestaShop where you choose your email list from a dropdown. Select the list you want to use and click **Save**.
5. That's it - your API Key, User ID, List, and Remarketing ID are all configured automatically.

### Option B: Manual Setup

1. Log in to your Newsman account at newsman.app.
2. Go to your account settings and copy your **API Key** and **User ID**.
3. In PrestaShop, go to **Admin > Modules > Newsman > Configure**.
4. Enable the module by setting **Enable Newsman** to **Yes**.
5. Enter your **User ID** and **API Key** in the corresponding fields.
6. Click **Save**. The connection status indicator below the API Key field will show whether the connection was successful.
7. Select your **Email List** from the dropdown. The lists are fetched from Newsman using the credentials you entered.
8. Optionally select a **Segment**.
9. Click **Save** again.

---

## Reconfigure with Newsman OAuth

If you need to reconnect the module to a different Newsman account, or if your credentials have changed, click the **Reconfigure** button on the configuration page. This will take you through the same OAuth flow described above - you will be redirected to the Newsman website to authorize access, then back to PrestaShop to select your email list. Your API Key, User ID, List, and Remarketing ID will be updated with the new credentials.

---

## Account Settings

- **Enable Newsman** - Enable or disable the Newsman module. When disabled, all Newsman features are inactive.

- **User ID** - Your Newsman User ID. Filled automatically if you used OAuth.

- **API Key** - Your Newsman API Key. Filled automatically if you used OAuth.

- **Connection Status** - Displayed below the API Key field. Shows a green "Connected to Newsman." indicator when the credentials are valid, or a red error message if the connection failed.

---

## General Settings

- **Email List** - Select the Newsman email list that will receive your subscribers. The dropdown shows all email lists from your Newsman account (SMS lists are excluded).

- **Segment** - Optionally select a segment within the chosen list. Segments let you organize subscribers into groups. If you don't use segments, leave this empty.

- **Double Opt-in** - When enabled, new subscribers receive a confirmation email and must click a link to confirm their subscription. This is recommended for GDPR compliance. When disabled, subscribers are added to the list immediately.

- **Send User IP Address** - When enabled, the visitor's IP address is sent to Newsman when they subscribe or unsubscribe. This can help with analytics and compliance. When disabled, the **Server IP** address is sent instead.

- **Server IP** - A fallback IP address used when "Send User IP Address" is turned off. You can usually leave this empty and the server IP will be detected automatically.

### Multi-Shop Notice

If you are running a PrestaShop multi-shop setup and multiple shops from different Shop Groups are linked to the same Newsman list, a warning banner will appear at the top of the General section. This configuration adds complexity that may not be fully resolved in the default version of the module. We recommend assigning a different list to each store.

---

## Remarketing Settings

Remarketing lets Newsman track what pages and products your visitors view, so you can send them personalized emails (e.g., abandoned cart reminders, product recommendations).

- **Enable Remarketing** - Enable or disable the remarketing tracking pixel on your store.

- **Remarketing ID** - This identifies your store in the Newsman tracking system. It is filled in automatically if you used OAuth. You can also find it in your Newsman account under remarketing settings.

- **Remarketing ID Status** - Displayed below the Remarketing ID field. Shows whether the Remarketing ID is valid (green) or invalid (red). If invalid, check the Remarketing ID value in your Newsman account.

- **Anonymize IP Address** - When turned on, visitor IP addresses are anonymized before being sent to Newsman. Recommended for GDPR compliance.

- **Send Telephone** - Include customer phone numbers in remarketing data. Only applies to logged-in customers who have provided a phone number.

---

## Developer Settings

These settings are intended for advanced users and developers. In most cases, you should leave them at their default values.

- **Log Severity** - Controls how much detail the module writes to its log file. Options range from **None** (no logging) through **Error**, **Warning**, **Notice**, **Info**, to **Debug** (maximum detail). The default is **Error**, which only logs problems. Set to **Debug** if you are troubleshooting an issue (but remember to set it back afterwards, as Debug mode creates large log files).

- **Log Clean Days** - Automatically deletes log files older than this number of days. Minimum 1 day.

- **API Timeout** - How many seconds the module waits for a response from Newsman before giving up. Minimum 5 seconds. The default works well for most setups.

- **Enable IP Restriction** - For development and testing only. When enabled, module functionality is restricted to the specified developer IP address. This option should not be enabled in a production environment.

- **Developer IP** - The IP address allowed when IP restriction is enabled. Only visible when Enable IP Restriction is set to Yes.

---

## Export Authorization Settings

- **Authenticate Token** - Displayed as a read-only masked value. Used for Newsman API authentication. It is exchanged automatically when the API key, User ID, enabled flag or list is changed, and when reconfigure or login with Newsman is done.

- **Header Name** - Custom HTTP header name for export authorization. Format: alphanumeric characters separated by hyphens. Set this value in the corresponding fields in Newsman App > E-Commerce > Coupons > Authorisation Header name, Newsman App > E-Commerce > Feed > a feed > Header Authorization, etc.

- **Header Key** - Custom HTTP header value for export authorization. Format: alphanumeric characters separated by hyphens. Set this value in the corresponding fields in Newsman App as described above.

If you connected via OAuth, the Authenticate Token is exchanged automatically and you generally do not need to configure the Header Name and Header Key manually. These fields are provided for advanced setups where you want to add an extra layer of security to data exports.

---

## Frequently Asked Questions

### How do I know if the connection is working?

After entering your credentials and saving, check the connection status indicator below the API Key field. It should show a green "Connected to Newsman." message. Also verify that the **Email List** dropdown shows your Newsman lists. Every Newsman account has at least one list by default, so if the credentials are correct the lists will appear.

### What is Double Opt-in?

When Double Opt-in is enabled, new subscribers receive a confirmation email with a link they must click to confirm their subscription. This ensures the email address is valid and that the person actually wants to subscribe. Double Opt-in is recommended for GDPR compliance.

### The remarketing scripts are not showing on my storefront. What should I do?

Verify that **Enable Remarketing** is set to Yes and that the **Remarketing ID** is valid (check the status indicator). Then view the page source of your storefront and search for the Newsman remarketing script. If the script is still not appearing, check the PrestaShop logs for errors.

### Where are the module logs?

The module writes logs that can be viewed in **Admin > Modules > Newsman > Logs** (log viewer built into the module). Log files are also stored on disk. The logging level is controlled in Developer Settings. Log files older than the configured number of days are automatically cleaned up.

### Can I configure different lists for different stores?

Yes. In a PrestaShop multi-shop setup, you can configure different lists, segments, remarketing IDs and other settings for each shop or shop group. We recommend assigning a different list to each store.

### What happens when a customer subscribes to the newsletter?

When a customer subscribes through the newsletter form, account registration, or their account settings page, the module automatically sends the subscription to Newsman using the configured list and segment. If Double Opt-in is enabled, Newsman will send a confirmation email first.
