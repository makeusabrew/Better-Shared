<?php
PathManager::loadPaths(
    array("/login", "login", "Users"),
    array("/logout", "logout", "Users"),
    array("/authed", "authed", "Users"),
    array("/me", "account", "Users")
);

