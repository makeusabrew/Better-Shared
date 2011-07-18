<?php
require_once('apps/twitterusers/deps/twitter-async/EpiCurl.php');
require_once('apps/twitterusers/deps/twitter-async/EpiOAuth.php');
require_once('apps/twitterusers/deps/twitter-async/EpiTwitter.php');

require_once('apps/bettershared/controllers/abstract.php');
class UsersController extends AbstractController {

    public function init() {
        parent::init();
        switch ($this->path->getAction()) {
            case "login":
            case "authed":
                if ($this->user->isAccount()) {
                    // go away
                    $this->redirect("/", "You're already logged in!");
                    throw new CoreException("Already logged in");
                }
                break;
            case "logout":
            case "my_questions":
                if ($this->user->isAccount() === false) {
                    $this->redirect("/");
                    throw new CoreException("Not Authed");
                }
                break;
            default:
                break;
        }
    }

    public function login() {
        try {
            $twitterObj = new EpiTwitter(Settings::getValue('twitter.consumer_key'), Settings::getValue('twitter.consumer_secret'));

            Log::debug('Redirecting to twitter auth URL');

            $authedUrl = $this->request->getBaseHref()."authed";
            if ($this->request->getVar('target') !== null) {
                $authedUrl .= "?target=".urlencode($this->request->getVar('target'));
            }

            return $this->redirect($twitterObj->getAuthenticateUrl(null, array(
                'oauth_callback' => $authedUrl,
            )));
        } catch (Exception $e) {
            // uh oh
            Log::debug('could not get oauth URL');
            return $this->redirect('/', 'Uh oh! Couldn\'t get twitter auth URL');
        }
    }

    public function logout() {
        $this->user->logout();
        return $this->redirect(array(
            "app" => "bettershared",
            "controller" => "Bettershared",
            "action" => "index",
        ), "Bye! Come back soon!");
    }

    public function authed() {
        $twitterObj = new EpiTwitter(Settings::getValue('twitter.consumer_key'), Settings::getValue('twitter.consumer_secret'));

        try {
            $twitterObj->setToken($this->request->getVar('oauth_token'));
            $token = $twitterObj->getAccessToken();
            $twitterObj->setToken($token->oauth_token, $token->oauth_token_secret);

            $details = $twitterObj->get_accountVerify_credentials();
        } catch (Exception $e) {
            return $this->redirect(array(
                "app" => "bettershared",
                "controller" => "Bettershared",
                "action" => "index",
            ), "Oops! There was a problem logging into Twitter. Please try again");
        }

        $user = Table::factory('Users')->findByTwitterId($details->id);
        if ($user === false) {
            if ($this->user->isGuest()) {
                // right. if this user has a *guest* account, we're okay to convert them to a proper user row
                // since they haven't ever interacted with the site as a full user (we have no DB record of them)
                Log::debug('converting guest ID ['.$this->user->getId().'] to full account for username ['.$details->screen_name.']');
                $user = $this->user;
            } else {
                // we know the user isn't a full account (they wouldn't get here), so they're brand new
                Log::debug('creating new account for username ['.$details->screen_name.']');
                $user = Table::factory('Users')->newObject();
            }
            $user->setValues(array(
                'username' => $details->screen_name,
                'twitter_id' => $details->id,
                'profile_image_url' => $details->profile_image_url,
                'oauth_token' => $token->oauth_token,
                'oauth_token_secret' => $token->oauth_token_secret,
                'type' => 'account',
                'identifier' => sha1(mt_rand().$token->oauth_token_secret),
            ));
            $user->save();
        } else {
            Log::debug('authenticating known user ['.$details->screen_name.']');
            // as you were
            if ($details->screen_name != $user->username ||
                $details->profile_image_url != $user->profile_image_url ||
                $token->oauth_token != $user->oauth_token ||
                $token->oauth_token_secret != $user->oauth_token_secret) {

                Log::debug('syncing twitter details...');
                $user->updateValues(array(
                    'username' => $details->screen_name,
                    'profile_image_url' => $details->profile_image_url,
                    'oauth_token' => $token->oauth_token,
                    'oauth_token_secret' => $token->oauth_token_secret,
                ));
                $user->save();
            }
        }

        // sets cookies too
        $user->addToSession();
        $this->user = $user;

        //
        // @todo obviously we shouldn't really be fetching *all* favourites like this on login!
        //
        try {
            $favourites = $twitterObj->get('/favorites/'.$this->user->username.'.json', array('include_entities' => true, 'count' => 200));
        } catch (EpiTwitterException $e) {
            // deal with it properly...
            die($e->getMessage());
        }
        foreach ($favourites as $favourite) {
            //echo $favourite->id_str;
            $favObj = Table::factory('Favourites')->findByTwitterId($favourite->id_str);
            if ($favObj === false) {
                // new favourite, bang it in
                $favObj = Table::factory('Favourites')->newObject();
                $data = array(
                    'created_at' => $favourite->created_at,
                    'twitter_id' => $favourite->id_str,
                    'text' => $favourite->text,
                    'author_username' => $favourite->user->screen_name,
                    'author_id' => $favourite->user->id,
                    'source' => $favourite->source,
                    'reply_username' => $favourite->in_reply_to_screen_name,
                    'reply_id' => $favourite->in_reply_to_status_id_str,
                );
                $favObj->setValues($data);
                $favObj->save();
                Log::debug('added favourite with id ['.$favObj->getId().']');
                if (isset($favourite->entities->urls) && is_array($favourite->entities->urls)) {
                    foreach ($favourite->entities->urls as $url) {
                        $data = array(
                            'favourite_id' => $favObj->getId(),
                            'url' => $url->url,
                            'indices' => implode(',', $url->indices),
                        );
                        if (isset($url->display_url)) {
                            $data['display_url'] = $url->display_url;
                        }
                        if (isset($url->expanded_url)) {
                            $data['expanded_url'] = $url->expanded_url;
                        }
                        $urlObj = Table::factory('FavouriteUrls')->newObject();
                        $urlObj->setValues($data);
                        $urlObj->save();
                        Log::debug('added URL ['.$url->url.'] for favourite ['.$favObj->getId().']');
                    }
                }
            }

            // ok, lovely. is this favourite in the user's list already?
            if ($this->user->hasFavouriteId($favObj->getId()) === false) {
                $this->user->addFavouriteId($favObj->getId());
            }
        }

        $message = "Hi, <strong>".$user->username."</strong>!";

        if ($this->request->getVar('target') !== null) {
            return $this->redirect($this->request->getVar('target'), $message);
        } else {
            return $this->redirect(array(
                "app" => "bettershared",
                "controller" => "Bettershared",
                "action" => "index",
            ), $message);
        }
    }

    public function account() {
        //
    }
}
