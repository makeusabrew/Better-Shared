<?php
PathManager::loadPaths(
    array("/", "index"),
    array("/favourite/(?P<id>\d+)", "view_favourite"),
    array("/favourite/(?P<id>\d+)/users", "view_favourite_users")
);
