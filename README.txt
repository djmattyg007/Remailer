Remailer

Remailer lets you use plus addressing in a cPanel shared hosting envrionment.

This code definitely needs a bit of work, but for the time being it suits my
needs and is generalised enough. There is nothing overly specific to cPanel
in this code, it's just the only environment I've tested it in. Pull requests
are definitely welcome!

To get this working, you need to go to the "Default Address" page in your
cPanel and tell it to pipe all unrouted email to email.php. You will also need
to create a PHP file named "whitelist.php" in the same directory as email.php.
This file should return a flat PHP array with a list of email addresses for
which plus addressing should be allowed.

This program is released into the public domain without any warranty.
