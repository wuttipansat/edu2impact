<?php
// News was removed from the site. Old links go to the home page. data/news.json and the MySQL rows are kept.
header('Location: index.php', true, 301);
exit;
