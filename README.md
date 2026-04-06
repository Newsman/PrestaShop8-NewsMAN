# PrestaShop 8.x NewsMAN Module

The [NewsMAN](https://www.newsmanapp.com) module for PrestaShop 8 facilitates seamless synchronization of your PrestaShop customers and subscribers with Newsman lists and segments. Simplify the connection between your shop and NewsMAN by installing this module and following the setup steps below. This process allows you to effortlessly sync customer and newsletter subscriber data, enable remarketing, and manage your email marketing campaigns.

> [!NOTE]
> For other PrestaShop versions, use the appropriate module:
> - **PrestaShop 9.x**: [Newsman/PrestaShop9-NewsMAN](https://github.com/Newsman/PrestaShop9-NewsMAN)
> - **PrestaShop 1.6.x – 1.7.x**: [Newsman/PrestaShop-Newsman](https://github.com/Newsman/PrestaShop-Newsman)

# Installation

## Manual installation (download archive and upload):
1. Download the latest **newsmanv8.zip** archive from [releases](https://github.com/Newsman/PrestaShop8-NewsMAN/releases) (Git tags 8.x.x-autoload, link in the right sidebar here on GitHub). The archive newsmanv8.zip contains the module with the generated `vendor/autoload.php` which is required.
2. Go to **Admin > Modules > Module Manager > Upload a module** and upload the **newsmanv8.zip** archive.
3. After installation, find **Newsman** in the module list and click **Configure**.
4. At this step you will need to click on the **Connect with Newsman** button and follow the steps to complete the configuration:
   - Authenticate in newsman.app.
   - Allow access to your Newsman account in your store.
   - Select the email list from the dropdown and save the settings.
5. After completing the OAuth flow, you will be redirected to the module configuration page where you can adjust all settings.
6. If there are any errors, repeat the configuration using the **Reconfigure** button on the settings page. Also you can check PrestaShop logs for more information in **Admin > Advanced Parameters > Logs** or in the Newsman log viewer at **Admin > Modules > Newsman > Logs**.
   You can increase the log level from the module configuration under Developer settings.

## Additional steps:
1. Review all settings on the Newsman configuration page for your preferred configuration.
2. Verify the storefront for Newsman remarketing JavaScript code.
3. You can also use the debugger in **newsman.app > Integrations > NewsMAN Remarketing > "Check installation"** button.
   The debugger is similar to Google GTM debugger and shows if the events are tracked correctly by NewsMAN remarketing.

## Manual installation (create archive from source):
1. Download from GitHub repository > top right corner **Code** > Download ZIP. Unarchive the downloaded file.
2. Go to the downloaded directory and run `composer install --no-dev` to install the dependencies.
3. Alternatively, use the build script: `./tools/developer/build-release-zip.sh /path/to/modules/newsmanv8 php8.2 /usr/local/bin/composer /tmp/newsmanv8.zip`
4. Upload the resulting **newsmanv8.zip** via **Admin > Modules > Module Manager > Upload a module**.

## Configuration

- [Configuration Guide (English)](https://github.com/Newsman/PrestaShop8-NewsMAN/blob/main/configuration-en.md)
- [Ghid de Configurare (Romana)](https://github.com/Newsman/PrestaShop8-NewsMAN/blob/main/configuration-ro.md)

# Plugin Description Features

## Subscription Forms & Pop-ups
- Craft visually appealing forms and pop-ups to engage potential leads through embedded newsletter signups or exit-intent popups.
- Maintain uniformity across devices for a seamless user experience.
- Integrate forms with automations to ensure swift responses and the delivery of welcoming emails.

## Contact Lists & Segments
- Efficiently import and synchronize contact lists from diverse sources to streamline data management.
- Apply segmentation techniques to precisely target audience segments based on demographics or behavior.

## Email & SMS Marketing Campaigns
- Effortlessly send out mass campaigns, newsletters, or promotions to a broad subscriber base.
- Customize campaigns for individual subscribers by incorporating their names and suggesting relevant products.
- Re-engage subscribers by reissuing campaigns to those who haven't opened the initial email.

## Email & SMS Marketing Automation
- Automate personalized product recommendations, follow-up emails, and strategies to address cart abandonment.
- Strategically tackle cart abandonment or highlight related products to encourage completed purchases.
- Collect post-purchase feedback to gauge customer satisfaction.

## Ecommerce Remarketing
- Reconnect with subscribers through targeted offers based on past interactions.
- Personalize interactions with exclusive offers or reminders based on user behavior or preferences.

## SMTP Transactional Emails
- Ensure the timely and reliable delivery of crucial messages, such as order confirmations or shipping notifications, through SMTP.

## Extended Email and SMS Statistics
- Gain comprehensive insights into open rates, click-through rates, conversion rates, and overall campaign performance for well-informed decision-making.

The NewsMAN Module for PrestaShop 8 simplifies your marketing efforts without hassle, enabling seamless communication with your audience.
