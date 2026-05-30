# Property Management Setup
1. First clone this repo and move the project folder into xampp/htdocs

2. Run mysql and Apache, import 'manaagement.sql', 'update.sql', 'update2.sql' into phpMyAdmin
- Click import tab, import management.sql
- Once database has been created click on it and go to import sql tab and import 'update.sql' and 'update2.sql'

3. Rename connect-db.sample.php to connect-db.php

4. Once running go to http://localhost/PropertyManagement/login.php

# Deployed App
The app has been deployed onto the Internet via a GCP Compute Machine VM. To access the app, go to http://34.139.240.24/login.php.
