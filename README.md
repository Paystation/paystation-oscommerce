# Paystation payment module for osCommerce

This integration is currently only tested up to osCommerce 2.3.4

## Requirements
* An account with [Paystation](https://www2.paystation.co.nz/)
* An HMAC key for your Paystation account, contact our support team if you do not already have this <support@paystation.co.nz>

## Installation

These instructions will guide you through installing the module and conducting a test transaction.

1. Copy the contents of the catalog directory of this folder into the catalog directory of your osCommerce installation.

2. Log into the adminstration pages of your osCommerce site.

3. Click the modules menu.

4. Select 'Payment' from the modules Menu

5. Click on "Paystation credit card gateway".
A panel will appear on the right with an 'install' button. Click it.

6. Click the Install Module button in the top right hand corner.

7. Find Paystation Payment Gateway in the list of payment modules and click on it.

8. The page will reload. This time near the top-right of the page, there is an Install Module button under the 
heading "Paystation Payment Gateway". Click on it.

9. Under the heading "Paystation Payment Gateway", click Edit.

10. The panel on the right now contains the Paystation module settings

11. Under Transaction Mode, select 'Test'

12. Under Paystation ID, enter your Paystation ID.

13. Under Gateway ID, enter the Gateway Id provided by Paystation.

14. Under HMAC key, enter the HMAC key supplied by Paystation.

15. We strongly suggest setting 'Enable Postback' to 'Yes' as it will allow the cart to capture payment results even if your customers re-direct is interrupted. However, if your development/test environment is local or on a network that cannot receive connections from the internet, you must set 'Enable Postback' to 'No'.

Your Paystation account needs to reflect your osCommerce settings accurately, otherwise order status will not update correctly. Email support@paystation.co.nz with your Paystation ID and advise whether 'Enable Postback' is set to 'Yes' or 'No' in your osCommerce settings.

16. Optionally, change the text under 'Checkout caption'. This is the text which is displayed next to the payment method
in the checkout.

17. Click Save.

18. The postback URL is: [host + oscommerce]/catalog/ext/modules/payment/paystation/postback.php

For example - www.yourwebsite.co.nz/oscommerce/catalog/ext/modules/payment/paystation/postback.php

The return URL is: [host + oscommerce]/catalog/checkout_process.php

For example - www.yourwebsite.co.nz/oscommerce/catalog/checkout_process.php
- send this to support@paystation.co.nz with your Paystation ID and request your Return URL and Postback URL to
be updated.
