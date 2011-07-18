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
            // okay, we know about this user - they've been here before and we have them in our DB
            if ($this->user->isGuest()) {
                // balls, this user's logged in as a guest.
                // if they've voted, and the DB user hasn't, then fine
                // if they haven't voted, and the db user has, then fine
                // what if BOTH have voted?
                // both voted is quite likely, because if they're already in the DB then they've been using
                // the system. and if they're a guest, then it's because.. well, they've voted
                // So, let's check: have the users ever voted on the same stuff?
                $votes = count(Table::factory('Votes')->findAllForBothUsers($user->getId(), $this->user->getId()));
                if ($votes > 0) {
                    // bad luck, we can't merge
                    Log::debug('can\'t merge guest user\'s votes with db user\'s because ['.$votes.'] votes overlap');
                    // by doing nothing we effectively orphan this guest account (we're about to add the DB row to session)
                    // which is a pity because it'll clutter up DB space, but it preserves vote integrity
                } else if (($votes = count(Table::factory('Votes')->findAllForUserWhereQuestionOwnedByOtherUser($this->user->getId(), $user->getId()))) > 0) {
                    // ah bugger, no overlap, but some questions we voted on as a guest are owned by the user we're now authing as - bail
                    Log::debug('can\'t merge guest user\'s votes with db user\'s because ['.$votes.'] votes are on DB user\'s own questions');
                } else {
                    // superb - no overlap :)
                    Log::debug('merging guest user votes into db user votes');
                    // let's be honest, the below is just an UPDATE
                    $this->user->convertVotesToUser($user->getId());
                    $this->user->delete();
                }
            }

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
