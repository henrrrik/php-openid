<?php

/**
 * This module documents the main interface with the OpenID consumer
 * library.  The only part of the library which has to be used and
 * isn't documented in full here is the store required to create an
 * Auth_OpenID_Consumer instance.  More on the abstract store type and
 * concrete implementations of it that are provided in the
 * documentation for the Auth_OpenID_Consumer constructor.
 *
 * OVERVIEW
 *
 * The OpenID identity verification process most commonly uses the
 * following steps, as visible to the user of this library:
 *
 *   1. The user enters their OpenID into a field on the consumer's
 *      site, and hits a login button.
 *   2. The consumer site discovers the user's OpenID server using the
 *      YADIS protocol.
 *   3. The consumer site sends the browser a redirect to the identity
 *      server.  This is the authentication request as described in
 *      the OpenID specification.
 *   4. The identity server's site sends the browser a redirect back
 *      to the consumer site.  This redirect contains the server's
 *      response to the authentication request.
 *
 * The most important part of the flow to note is the consumer's site
 * must handle two separate HTTP requests in order to perform the full
 * identity check.
 *
 * LIBRARY DESIGN
 * 
 * This consumer library is designed with that flow in mind.  The goal
 * is to make it as easy as possible to perform the above steps
 * securely.
 *
 * At a high level, there are two important parts in the consumer
 * library.  The first important part is this module, which contains
 * the interface to actually use this library.  The second is the
 * Auth_OpenID_Interface class, which describes the interface to use
 * if you need to create a custom method for storing the state this
 * library needs to maintain between requests.
 *
 * In general, the second part is less important for users of the
 * library to know about, as several implementations are provided
 * which cover a wide variety of situations in which consumers may use
 * the library.
 *
 * This module contains a class, Auth_OpenID_Consumer, with methods
 * corresponding to the actions necessary in each of steps 2, 3, and 4
 * described in the overview.  Use of this library should be as easy
 * as creating an Auth_OpenID_Consumer instance and calling the
 * methods appropriate for the action the site wants to take.
 *
 * STORES AND DUMB MODE
 *
 * OpenID is a protocol that works best when the consumer site is able
 * to store some state.  This is the normal mode of operation for the
 * protocol, and is sometimes referred to as smart mode.  There is
 * also a fallback mode, known as dumb mode, which is available when
 * the consumer site is not able to store state.  This mode should be
 * avoided when possible, as it leaves the implementation more
 * vulnerable to replay attacks.
 *
 * The mode the library works in for normal operation is determined by
 * the store that it is given.  The store is an abstraction that
 * handles the data that the consumer needs to manage between http
 * requests in order to operate efficiently and securely.
 *
 * Several store implementation are provided, and the interface is
 * fully documented so that custom stores can be used as well.  See
 * the documentation for the Auth_OpenID_Consumer class for more
 * information on the interface for stores.  The implementations that
 * are provided allow the consumer site to store the necessary data in
 * several different ways, including several SQL databases and normal
 * files on disk.
 *
 * There is an additional concrete store provided that puts the system
 * in dumb mode.  This is not recommended, as it removes the library's
 * ability to stop replay attacks reliably.  It still uses time-based
 * checking to make replay attacks only possible within a small
 * window, but they remain possible within that window.  This store
 * should only be used if the consumer site has no way to retain data
 * between requests at all.
 *
 * IMMEDIATE MODE
 *
 * In the flow described above, the user may need to confirm to the
 * lidentity server that it's ok to authorize his or her identity.
 * The server may draw pages asking for information from the user
 * before it redirects the browser back to the consumer's site.  This
 * is generally transparent to the consumer site, so it is typically
 * ignored as an implementation detail.
 *
 * There can be times, however, where the consumer site wants to get a
 * response immediately.  When this is the case, the consumer can put
 * the library in immediate mode.  In immediate mode, there is an
 * extra response possible from the server, which is essentially the
 * server reporting that it doesn't have enough information to answer
 * the question yet.  In addition to saying that, the identity server
 * provides a URL to which the user can be sent to provide the needed
 * information and let the server finish handling the original
 * request.
 *
 * USING THIS LIBRARY
 *
 * Integrating this library into an application is usually a
 * relatively straightforward process.  The process should basically
 * follow this plan:
 *
 * Add an OpenID login field somewhere on your site.  When an OpenID
 * is entered in that field and the form is submitted, it should make
 * a request to the your site which includes that OpenID URL.
 *
 * First, the application should instantiate the Auth_OpenID_Consumer
 * class using the store of choice (Auth_OpenID_FileStore or one of
 * the SQL-based stores).  If the application has any sort of session
 * framework that provides per-client state management, a dict-like
 * object to access the session should be passed as the optional
 * second parameter.  (The default behavior is to use PHP's standard
 * session machinery.)
 *
 * Next, the application should call the Auth_OpenID_Consumer object's
 * 'begin' method.  This method takes the OpenID URL.  The 'begin'
 * method returns an Auth_OpenID_AuthRequest object.
 *
 * Next, the application should call the 'redirectURL' method of the
 * Auth_OpenID_AuthRequest object.  The 'return_to' URL parameter is
 * the URL that the OpenID server will send the user back to after
 * attempting to verify his or her identity.  The 'trust_root' is the
 * URL (or URL pattern) that identifies your web site to the user when
 * he or she is authorizing it.  Send a redirect to the resulting URL
 * to the user's browser.
 *
 * That's the first half of the authentication process.  The second
 * half of the process is done after the user's ID server sends the
 * user's browser a redirect back to your site to complete their
 * login.
 *
 * When that happens, the user will contact your site at the URL given
 * as the 'return_to' URL to the Auth_OpenID_AuthRequest::redirectURL
 * call made above.  The request will have several query parameters
 * added to the URL by the identity server as the information
 * necessary to finish the request.
 *
 * Lastly, instantiate an Auth_OpenID_Consumer instance as above and
 * call its 'complete' method, passing in all the received query
 * arguments.
 *
 * There are multiple possible return types possible from that
 * method. These indicate the whether or not the login was successful,
 * and include any additional information appropriate for their type.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: See the COPYING file included in this distribution.
 *
 * @package OpenID
 * @author JanRain, Inc. <openid@janrain.com>
 * @copyright 2005 Janrain, Inc.
 * @license http://www.gnu.org/copyleft/lesser.html LGPL
 */

/**
 * Require utility classes and functions for the consumer.
 */
require_once "Auth/OpenID.php";
require_once "Auth/OpenID/HMACSHA1.php";
require_once "Auth/OpenID/Association.php";
require_once "Auth/OpenID/CryptUtil.php";
require_once "Auth/OpenID/DiffieHellman.php";
require_once "Auth/OpenID/KVForm.php";
require_once "Auth/OpenID/Discover.php";
require_once "Services/Yadis/Manager.php";
require_once "Services/Yadis/XRI.php";

/**
 * This is the status code returned when the complete method returns
 * successfully.
 */
define('Auth_OpenID_SUCCESS', 'success');

/**
 * Status to indicate cancellation of OpenID authentication.
 */
define('Auth_OpenID_CANCEL', 'cancel');

/**
 * This is the status code completeAuth returns when the value it
 * received indicated an invalid login.
 */
define('Auth_OpenID_FAILURE', 'failure');

/**
 * This is the status code completeAuth returns when the
 * {@link Auth_OpenID_Consumer} instance is in immediate mode, and the
 * identity server sends back a URL to send the user to to complete his
 * or her login.
 */
define('Auth_OpenID_SETUP_NEEDED', 'setup needed');

/**
 * This is the status code beginAuth returns when the page fetched
 * from the entered OpenID URL doesn't contain the necessary link tags
 * to function as an identity page.
 */
define('Auth_OpenID_PARSE_ERROR', 'parse error');

/**
 * This is the characters that the nonces are made from.
 */
define('Auth_OpenID_DEFAULT_NONCE_CHRS',"abcdefghijklmnopqrstuvwxyz" .
       "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789");

/**
 * This is the number of seconds the tokens generated by this library
 * will be valid for.  If you want to change the lifetime of a token,
 * set this value to the desired lifespan, in seconds.
 */
define('Auth_OpenID_DEFAULT_TOKEN_LIFETIME', 60 * 5); // five minutes

/**
 * An OpenID consumer implementation that performs discovery and does
 * session management.  See the Consumer.php file documentation for
 * more information.
 *
 * @package OpenID
 */
class Auth_OpenID_Consumer {

    /**
     * @access private
     */
    var $session_key_prefix = "_openid_consumer_";

    /**
     * @access private
     */
    var $_token_suffix = "last_token";

    /**
     * Initialize a Consumer instance.
     *
     * You should create a new instance of the Consumer object with
     * every HTTP request that handles OpenID transactions.
     *
     * @param Auth_OpenID_OpenIDStore $store This must be an object
     * that implements the interface in {@link
     * Auth_OpenID_OpenIDStore}.  Several concrete implementations are
     * provided, to cover most common use cases.  For stores backed by
     * MySQL, PostgreSQL, or SQLite, see the {@link
     * Auth_OpenID_SQLStore} class and its sublcasses.  For a
     * filesystem-backed store, see the {@link Auth_OpenID_FileStore}
     * module.  As a last resort, if it isn't possible for the server
     * to store state at all, an instance of {@link
     * Auth_OpenID_DumbStore} can be used.  This should be an absolute
     * last resort, though, as it makes the consumer vulnerable to
     * replay attacks over the lifespan of the tokens the library
     * creates.
     *
     * @param mixed session An object which implements the interface
     * of the Services_Yadis_Session class.  Particularly, this object
     * is expected to have these methods: get($key), set($key,
     * $value), and del($key).  This defaults to a session object
     * which wraps PHP's native session machinery.  You should only
     * need to pass something here if you have your own sessioning
     * implementation.
     */
    function Auth_OpenID_Consumer(&$store, $session = null)
    {
        if ($session === null) {
            $session = new Services_Yadis_PHPSession();
        }

        $this->session =& $session;
        $this->consumer =& new Auth_OpenID_GenericConsumer($store);
        $this->_token_key = $this->session_key_prefix . $this->_token_suffix;
    }

    /**
     * Start the OpenID authentication process. See steps 1-2 in the
     * overview at the top of this file.
     *
     * @param User_url: Identity URL given by the user. This method
     * performs a textual transformation of the URL to try and make
     * sure it is normalized. For example, a user_url of example.com
     * will be normalized to http://example.com/ normalizing and
     * resolving any redirects the server might issue.
     *
     * @return Auth_OpenID_AuthRequest $auth_request An object
     * containing the discovered information will be returned, with a
     * method for building a redirect URL to the server, as described
     * in step 3 of the overview. This object may also be used to add
     * extension arguments to the request, using its 'addExtensionArg'
     * method.
     */
    function begin($user_url)
    {
        $discoverMethod = '_Auth_OpenID_discoverServiceList';
        $openid_url = $user_url;

        if (Services_Yadis_identifierScheme($user_url) == 'XRI') {
            $discoverMethod = '_Auth_OpenID_discoverXRIServiceList';
        } else {
            $openid_url = Auth_OpenID::normalizeUrl($user_url);
        }

        $disco = new Services_Yadis_Discovery($this->session,
                                              $openid_url,
                                              $this->session_key_prefix);

        // Set the 'stale' attribute of the manager.  If discovery
        // fails in a fatal way, the stale flag will cause the manager
        // to be cleaned up next time discovery is attempted.
        $m = $disco->getManager();
        if ($m) {
            $m->stale = true;
            $disco->session->set($disco->session_key,
                                 serialize($m));
        }

        $endpoint = $disco->getNextService($discoverMethod,
                                           $this->consumer->fetcher);

        // Reset the 'stale' attribute of the manager.
        $m =& $disco->getManager();
        if ($m) {
            $m->stale = false;
            $disco->session->set($disco->session_key,
                                 serialize($m));
        }

        if ($endpoint === null) {
            return null;
        } else {
            return $this->beginWithoutDiscovery($endpoint);
        }
    }

    /**
     * Start OpenID verification without doing OpenID server
     * discovery. This method is used internally by Consumer.begin
     * after discovery is performed, and exists to provide an
     * interface for library users needing to perform their own
     * discovery.
     *
     * @param Auth_OpenID_ServiceEndpoint $endpoint an OpenID service
     * endpoint descriptor.
     *
     * @return Auth_OpenID_AuthRequest $auth_request An OpenID
     * authentication request object.
     */
    function &beginWithoutDiscovery($endpoint)
    {
        $auth_req = $this->consumer->begin($endpoint);
        $this->session->set($this->_token_key, $auth_req->token);
        return $auth_req;
    }

    /**
     * Called to interpret the server's response to an OpenID
     * request. It is called in step 4 of the flow described in the
     * consumer overview.
     *
     * @param array $query An array of the query parameters (key =>
     * value pairs) for this HTTP request.
     *
     * @return Auth_OpenID_ConsumerResponse $response A instance of an
     * Auth_OpenID_ConsumerResponse subclass. The type of response is
     * indicated by the status attribute, which will be one of
     * SUCCESS, CANCEL, FAILURE, or SETUP_NEEDED.
     */
    function complete($query)
    {
        $query = Auth_OpenID::fixArgs($query);

        $token = $this->session->get($this->_token_key);

        if ($token === null) {
            $response = new Auth_OpenID_FailureResponse(null,
                                                   'No session state found');
        } else {
            $response = $this->consumer->complete($query, $token);
            $this->session->del($this->_token_key);
        }

        if (in_array($response->status, array(Auth_OpenID_SUCCESS,
                                              Auth_OpenID_CANCEL))) {
            if ($response->identity_url !== null) {
                $disco = new Services_Yadis_Discovery($this->session,
                                                  $response->identity_url,
                                                  $this->session_key_prefix);
                $disco->cleanup();
            }
        }

        return $response;
    }
}

/**
 * This class is the interface to the OpenID consumer logic.
 * Instances of it maintain no per-request state, so they can be
 * reused (or even used by multiple threads concurrently) as needed.
 *
 * @package OpenID
 * @access private
 */
class Auth_OpenID_GenericConsumer {
    /**
     * This consumer's store object.
     */
    var $store;

    /**
     * @access private
     */
    var $_use_assocs;

    /**
     * This is the number of characters in the generated nonce for
     * each transaction.
     */
    var $nonce_len = 8;

    /**
     * What characters are allowed in nonces
     */
    var $nonce_chrs = Auth_OpenID_DEFAULT_NONCE_CHRS;

    /**
     * How long should an authentication session stay good?
     *
     * In units of sections. Shorter times mean less opportunity for
     * attackers, longer times mean less chance of a user's session
     * timing out.
     */
    var $token_lifetime = Auth_OpenID_DEFAULT_TOKEN_LIFETIME;

    /**
     * This method initializes a new {@link Auth_OpenID_Consumer}
     * instance to access the library.
     *
     * @param Auth_OpenID_OpenIDStore $store This must be an object
     * that implements the interface in {@link Auth_OpenID_OpenIDStore}.
     * Several concrete implementations are provided, to cover most common use
     * cases.  For stores backed by MySQL, PostgreSQL, or SQLite, see
     * the {@link Auth_OpenID_SQLStore} class and its sublcasses.  For a
     * filesystem-backed store, see the {@link Auth_OpenID_FileStore} module.
     * As a last resort, if it isn't possible for the server to store
     * state at all, an instance of {@link Auth_OpenID_DumbStore} can be used.
     * This should be an absolute last resort, though, as it makes the
     * consumer vulnerable to replay attacks over the lifespan of the
     * tokens the library creates.
     *
     * @param bool $immediate This is an optional boolean value.  It
     * controls whether the library uses immediate mode, as explained
     * in the module description.  The default value is False, which
     * disables immediate mode.
     */
    function Auth_OpenID_GenericConsumer(&$store)
    {
        if ($store === null) {
            trigger_error("Must supply non-null store to create consumer",
                          E_USER_ERROR);
            return null;
        }

        $this->store =& $store;
        $this->_use_assocs =
            !(defined('Auth_OpenID_NO_MATH_SUPPORT') ||
              $this->store->isDumb());

        $this->fetcher = Services_Yadis_Yadis::getHTTPFetcher();
    }

    function begin($service_endpoint)
    {
        $nonce = $this->_createNonce();
        $token = $this->_genToken($service_endpoint->identity_url,
                                  $service_endpoint->getServerID(),
                                  $service_endpoint->server_url);
        $assoc = $this->_getAssociation($service_endpoint->server_url);
        $r = new Auth_OpenID_AuthRequest($token, $assoc, $service_endpoint);
        $r->return_to_args['nonce'] = $nonce;
        return $r;
    }

    function complete($query, $token)
    {
        $mode = Auth_OpenID::arrayGet($query, 'openid.mode',
                                      '<no mode specified>');

        $pieces = $this->_splitToken($token);
        if ($pieces === null) {
            $pieces = array(null, null, null);
        }

        list($identity_url, $delegate, $server_url) = $pieces;

        if ($mode == Auth_OpenID_CANCEL) {
            return new Auth_OpenID_CancelResponse($identity_url);
        } else if ($mode == 'error') {
            $error = Auth_OpenID::arrayGet($query, 'openid.error');
            return new Auth_OpenID_FailureResponse($identity_url, $error);
        } else if ($mode == 'id_res') {
            if ($identity_url === null) {
                return new Auth_OpenID_FailureResponse($identity_url,
                                                    "No session state found");
            }

            $response = $this->_doIdRes($query, $identity_url, $delegate,
                                        $server_url);

            if ($response === null) {
                return new Auth_OpenID_FailureResponse($identity_url,
                                                       "HTTP request failed");
            }
            if ($response->status == Auth_OpenID_SUCCESS) {
                return $this->_checkNonce($response,
                                          Auth_OpenID::arrayGet($query,
                                                                'nonce'));
            } else {
                return $response;
            }
        } else {
            return new Auth_OpenID_FailureResponse($identity_url,
                                           sprintf("Invalid openid.mode '%s'",
                                                   $mode));
        }
    }

    /**
     * @access private
     */
    function _doIdRes($query, $consumer_id, $server_id, $server_url)
    {
        $user_setup_url = Auth_OpenID::arrayGet($query,
                                                'openid.user_setup_url');

        if ($user_setup_url !== null) {
            return new Auth_OpenID_SetupNeededResponse($consumer_id,
                                                       $user_setup_url);
        }

        $return_to = Auth_OpenID::arrayGet($query, 'openid.return_to', null);
        $server_id2 = Auth_OpenID::arrayGet($query, 'openid.identity', null);
        $assoc_handle = Auth_OpenID::arrayGet($query,
                                             'openid.assoc_handle', null);

        if (($return_to === null) ||
            ($server_id === null) ||
            ($assoc_handle === null)) {
            return new Auth_OpenID_FailureResponse($consumer_id,
                                                   "Missing required field");
        }

        if ($server_id != $server_id2) {
            return new Auth_OpenID_FailureResponse($consumer_id,
                                             "Server ID (delegate) mismatch");
        }

        $signed = Auth_OpenID::arrayGet($query, 'openid.signed');

        $assoc = $this->store->getAssociation($server_url, $assoc_handle);

        if ($assoc === null) {
            // It's not an association we know about.  Dumb mode is
            // our only possible path for recovery.
            if ($this->_checkAuth($query, $server_url)) {
                return new Auth_OpenID_SuccessResponse($consumer_id, $query,
                                                       $signed);
            } else {
                return new Auth_OpenID_FailureResponse($consumer_id,
                                       "Server denied check_authentication");
            }
        }

        if ($assoc->getExpiresIn() <= 0) {
            $msg = sprintf("Association with %s expired", $server_url);
            return new Auth_OpenID_FailureResponse($consumer_id, $msg);
        }

        // Check the signature
        $sig = Auth_OpenID::arrayGet($query, 'openid.sig', null);
        if (($sig === null) ||
            ($signed === null)) {
            return new Auth_OpenID_FailureResponse($consumer_id,
                                               "Missing argument signature");
        }

        $signed_list = explode(",", $signed);
        $v_sig = $assoc->signDict($signed_list, $query);

        if ($v_sig != $sig) {
            return new Auth_OpenID_FailureResponse($consumer_id,
                                                   "Bad signature");
        }

        return Auth_OpenID_SuccessResponse::fromQuery($consumer_id,
                                                      $query, $signed);
    }

    /**
     * @access private
     */
    function _checkAuth($query, $server_url)
    {
        $request = $this->_createCheckAuthRequest($query);
        if ($request === null) {
            return false;
        }

        $response = $this->_makeKVPost($request, $server_url);
        if ($response == null) {
            return false;
        }

        return $this->_processCheckAuthResponse($response, $server_url);
    }

    /**
     * @access private
     */
    function _createCheckAuthRequest($query)
    {
        $signed = Auth_OpenID::arrayGet($query, 'openid.signed', null);
        if ($signed === null) {
            return null;
        }

        $whitelist = array('assoc_handle', 'sig',
                           'signed', 'invalidate_handle');

        $signed = array_merge(explode(",", $signed), $whitelist);

        $check_args = array();

        foreach ($query as $key => $value) {
            if (in_array(substr($key, 7), $signed)) {
                $check_args[$key] = $value;
            }
        }

        $check_args['openid.mode'] = 'check_authentication';
        return $check_args;
    }

    /**
     * @access private
     */
    function _processCheckAuthResponse($response, $server_url)
    {
        $is_valid = Auth_OpenID::arrayGet($response, 'is_valid', 'false');

        $invalidate_handle = Auth_OpenID::arrayGet($response,
                                                   'invalidate_handle');

        if ($invalidate_handle !== null) {
            $this->store->removeAssociation($server_url,
                                            $invalidate_handle);
        }

        if ($is_valid == 'true') {
            return true;
        }

        return false;
    }

    /**
     * @access private
     */
    function _makeKVPost($args, $server_url)
    {
        $mode = $args['openid.mode'];

        $pairs = array();
        foreach ($args as $k => $v) {
            $v = urlencode($v);
            $pairs[] = "$k=$v";
        }

        $body = implode("&", $pairs);

        $resp = $this->fetcher->post($server_url, $body);

        if ($resp === null) {
            return null;
        }

        $response = Auth_OpenID_KVForm::toArray($resp->body);

        if ($resp->status == 400) {
            return null;
        } else if ($resp->status != 200) {
            return null;
        }

        return $response;
    }

    /**
     * @access private
     */
    function _checkNonce($response, $nonce)
    {
        $parsed_url = parse_url($response->getReturnTo());
        $query_str = @$parsed_url['query'];
        $query = array();
        parse_str($query_str, $query);

        $found = false;

        foreach ($query as $k => $v) {
            if ($k == 'nonce') {
                if ($v != $nonce) {
                    return new Auth_OpenID_FailureResponse(
                                                  $response->identity_url,
                                                  "Nonce mismatch");
                } else {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            return new Auth_OpenID_FailureResponse($response->identity_url,
                                 sprintf("Nonce missing from return_to: %s",
                                         $response->getReturnTo()));
        }

        if (!$this->store->useNonce($nonce)) {
            return new Auth_OpenID_FailureResponse($response->identity_url,
                                                   "Nonce missing from store");
        }

        return $response;
    }

    /**
     * @access private
     */
    function _createNonce()
    {
        $nonce = Auth_OpenID_CryptUtil::randomString($this->nonce_len,
                                                     $this->nonce_chrs);
        $this->store->storeNonce($nonce);
        return $nonce;
    }

    /**
     * @access protected
     */
    function _createDiffieHellman()
    {
        return new Auth_OpenID_DiffieHellman();
    }

    /**
     * @access private
     */
    function _getAssociation($server_url, $replace = false)
    {
        if (!$this->_use_assocs) {
            return null;
        }

        $assoc = $this->store->getAssociation($server_url);

        if (($assoc === null) ||
            ($replace && ($assoc->getExpiresIn() < $this->token_lifetime))) {

            $args = array(
                          'openid.mode' =>  'associate',
                          'openid.assoc_type' => 'HMAC-SHA1',
                          );

            $dh = $this->_createDiffieHellman();
            $args = array_merge($args, $dh->getAssocArgs());
            $body = Auth_OpenID::httpBuildQuery($args);

            $assoc = $this->_fetchAssociation($dh, $server_url, $body);
        }

        return $assoc;
    }

    /**
     * @access private
     */
    function _genToken($consumer_id, $server_id, $server_url)
    {
        $timestamp = strval(time());
        $elements = array($timestamp, $consumer_id, $server_id, $server_url);

        $joined = implode("\x00", $elements);
        $sig = Auth_OpenID_HMACSHA1($this->store->getAuthKey(),
                                              $joined);

        return base64_encode($sig . $joined);
    }

    /**
     * @access private
     */
    function _splitToken($token)
    {
        $token = base64_decode($token);
        if (strlen($token) < 20) {
            return null;
        }

        $sig = substr($token, 0, 20);
        $joined = substr($token, 20);
        $check_sig = Auth_OpenID_HMACSHA1($this->store->getAuthKey(), $joined);
        if ($check_sig != $sig) {
            return null;
        }

        $split = explode("\x00", $joined);
        if (count($split) != 4) {
            return null;
        }

        $ts = intval($split[0]);
        if ($ts == 0) {
            return null;
        }

        if ($ts + $this->token_lifetime < time()) {
            return null;
        }

        return array_slice($split, 1);
    }

    /**
     * @access private
     */
    function _fetchAssociation($dh, $server_url, $body)
    {
        $ret = @$this->fetcher->post($server_url, $body);

        if ($ret === null) {
            $fmt = 'Getting association: failed to fetch URL: %s';
            trigger_error(sprintf($fmt, $server_url), E_USER_NOTICE);
            return null;
        }

        $results = Auth_OpenID_KVForm::toArray($ret->body);
        if ($ret->status == 400) {
            $error = Auth_OpenID::arrayGet($results, 'error',
                                           '<no message from server>');

            $fmt = 'Getting association: error returned from server %s: %s';
            trigger_error(sprintf($fmt, $server_url, $error), E_USER_NOTICE);
            return null;
        } else if ($ret->status != 200) {
            $fmt = 'Getting association: bad status code from server %s: %s';
            $msg = sprintf($fmt, $server_url, $ret->status);
            trigger_error($msg, E_USER_NOTICE);
            return null;
        }

        $results = Auth_OpenID_KVForm::toArray($ret->body);

        return $this->_parseAssociation($results, $dh, $server_url);
    }

    /**
     * @access private
     */
    function _parseAssociation($results, $dh, $server_url)
    {
        $required_keys = array('assoc_type', 'assoc_handle',
                               'dh_server_public', 'enc_mac_key');

        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $results)) {
                $fmt = "associate: missing key in response from %s: %s";
                $msg = sprintf($fmt, $server_url, $key);
                trigger_error($msg, E_USER_NOTICE);
                return null;
            }
        }

        $assoc_type = $results['assoc_type'];
        if ($assoc_type != 'HMAC-SHA1') {
            $fmt = 'Unsupported assoc_type returned from server %s: %s';
            $msg = sprintf($fmt, $server_url, $assoc_type);
            trigger_error($msg, E_USER_NOTICE);
            return null;
        }

        $assoc_handle = $results['assoc_handle'];
        $expires_in = intval(Auth_OpenID::arrayGet($results, 'expires_in',
                             '0'));

        $session_type = Auth_OpenID::arrayGet($results, 'session_type', null);
        if ($session_type === null) {
            $secret = base64_decode($results['mac_key']);
        } else {
            $fmt = 'Unsupported session_type returned from server %s: %s';
            if ($session_type != 'DH-SHA1') {
                $msg = sprintf($fmt, $server_url, $session_type);
                trigger_error($msg, E_USER_NOTICE);
                return null;
            }

            $secret = $dh->consumerFinish($results);
        }

        $assoc = Auth_OpenID_Association::fromExpiresIn($expires_in,
                                                       $assoc_handle,
                                                       $secret,
                                                       $assoc_type);

        $this->store->storeAssociation($server_url, $assoc);
        return $assoc;
    }
}

/**
 * This class represents an authentication request from a consumer to
 * an OpenID server.
 *
 * @package OpenID
 */
class Auth_OpenID_AuthRequest {

    /**
     * Initialize an authentication request with the specified token,
     * association, and endpoint.
     *
     * Users of this library should not create instances of this
     * class.  Instances of this class are created by the library when
     * needed.
     */
    function Auth_OpenID_AuthRequest($token, $assoc, $endpoint)
    {
        $this->assoc = $assoc;
        $this->endpoint = $endpoint;
        $this->extra_args = array();
        $this->return_to_args = array();
        $this->token = $token;
    }

    /**
     * Add an extension argument to this OpenID authentication
     * request.
     *
     * Use caution when adding arguments, because they will be
     * URL-escaped and appended to the redirect URL, which can easily
     * get quite long.
     *
     * @param string $namespace The namespace for the extension. For
     * example, the simple registration extension uses the namespace
     * 'sreg'.
     *
     * @param string $key The key within the extension namespace. For
     * example, the nickname field in the simple registration
     * extension's key is 'nickname'.
     *
     * @param string $value The value to provide to the server for
     * this argument.
     */
    function addExtensionArg($namespace, $key, $value)
    {
        $arg_name = implode('.', array('openid', $namespace, $key));
        $this->extra_args[$arg_name] = $value;
    }

    /**
     * Compute the appropriate redirection URL for this request based
     * on a specified trust root and return-to.
     *
     * @param string $trust_root The trust root URI for your
     * application.
     *
     * @param string$ $return_to The return-to URL to be used when the
     * OpenID server redirects the user back to your site.
     *
     * @return string $redirect_url The resulting redirect URL that
     * you should send to the user agent.
     */
    function redirectURL($trust_root, $return_to, $immediate=false)
    {
        if ($immediate) {
            $mode = 'checkid_immediate';
        } else {
            $mode = 'checkid_setup';
        }

        $return_to = Auth_OpenID::appendArgs($return_to, $this->return_to_args);

        $redir_args = array(
            'openid.mode' => $mode,
            'openid.identity' => $this->endpoint->getServerID(),
            'openid.return_to' => $return_to,
            'openid.trust_root' => $trust_root);

        if ($this->assoc) {
            $redir_args['openid.assoc_handle'] = $this->assoc->handle;
        }

        $redir_args = array_merge($redir_args, $this->extra_args);

        return Auth_OpenID::appendArgs($this->endpoint->server_url,
                                       $redir_args);
    }
}

/**
 * The base class for responses from the Auth_OpenID_Consumer.
 *
 * @package OpenID
 */
class Auth_OpenID_ConsumerResponse {
    var $status = null;
}

/**
 * A response with a status of Auth_OpenID_SUCCESS. Indicates that
 * this request is a successful acknowledgement from the OpenID server
 * that the supplied URL is, indeed controlled by the requesting
 * agent.  This has three relevant attributes:
 *
 * identity_url - The identity URL that has been authenticated
 *
 * signed_args - The arguments in the server's response that were
 * signed and verified.
 *
 * status - Auth_OpenID_SUCCESS.
 *
 * @package OpenID
 */
class Auth_OpenID_SuccessResponse extends Auth_OpenID_ConsumerResponse {
    var $status = Auth_OpenID_SUCCESS;

    /**
     * @access private
     */
    function Auth_OpenID_SuccessResponse($identity_url, $signed_args)
    {
        $this->identity_url = $identity_url;
        $this->signed_args = $signed_args;
    }

    /**
     * @access private
     */
    function fromQuery($identity_url, $query, $signed)
    {
        $signed_args = array();
        foreach (explode(",", $signed) as $field_name) {
            $field_name = 'openid.' . $field_name;
            $signed_args[$field_name] = Auth_OpenID::arrayGet($query,
                                                              $field_name, '');
        }
        return new Auth_OpenID_SuccessResponse($identity_url, $signed_args);
    }

    /**
     * Extract signed extension data from the server's response.
     *
     * @param string $prefix The extension namespace from which to
     * extract the extension data.
     */
    function extensionResponse($prefix)
    {
        $response = array();
        $prefix = sprintf('openid.%s.', $prefix);
        $prefix_len = strlen($prefix);
        foreach ($this->signed_args as $k => $v) {
            if (strpos($k, $prefix) === 0) {
                $response_key = substr($k, $prefix_len);
                $response[$response_key] = $v;
            }
        }

        return $response;
    }

    /**
     * Get the openid.return_to argument from this response.
     *
     * This is useful for verifying that this request was initiated by
     * this consumer.
     *
     * @return string $return_to The return_to URL supplied to the
     * server on the initial request, or null if the response did not
     * contain an 'openid.return_to' argument.
    */
    function getReturnTo()
    {
        return Auth_OpenID::arrayGet($this->signed_args, 'openid.return_to');
    }
}

/**
 * A response with a status of Auth_OpenID_FAILURE. Indicates that the
 * OpenID protocol has failed. This could be locally or remotely
 * triggered.  This has three relevant attributes:
 *
 * identity_url - The identity URL for which authentication was
 * attempted, if it can be determined.  Otherwise, null.
 *
 * message - A message indicating why the request failed, if one is
 * supplied.  Otherwise, null.
 *
 * status - Auth_OpenID_FAILURE.
 *
 * @package OpenID
 */
class Auth_OpenID_FailureResponse extends Auth_OpenID_ConsumerResponse {
    var $status = Auth_OpenID_FAILURE;

    function Auth_OpenID_FailureResponse($identity_url = null, $message = null)
    {
        $this->identity_url = $identity_url;
        $this->message = $message;
    }
}

/**
 * A response with a status of Auth_OpenID_CANCEL. Indicates that the
 * user cancelled the OpenID authentication request.  This has two
 * relevant attributes:
 *
 * identity_url - The identity URL for which authentication was
 * attempted, if it can be determined.  Otherwise, null.
 *
 * status - Auth_OpenID_SUCCESS.
 *
 * @package OpenID
 */
class Auth_OpenID_CancelResponse extends Auth_OpenID_ConsumerResponse {
    var $status = Auth_OpenID_CANCEL;

    function Auth_OpenID_CancelResponse($identity_url = null)
    {
        $this->identity_url = $identity_url;
    }
}

/**
 * A response with a status of Auth_OpenID_SETUP_NEEDED. Indicates
 * that the request was in immediate mode, and the server is unable to
 * authenticate the user without further interaction.
 *
 * identity_url - The identity URL for which authentication was
 * attempted.
 *
 * setup_url - A URL that can be used to send the user to the server
 * to set up for authentication. The user should be redirected in to
 * the setup_url, either in the current window or in a new browser
 * window.
 *
 * status - Auth_OpenID_SETUP_NEEDED.
 *
 * @package OpenID
 */
class Auth_OpenID_SetupNeededResponse extends Auth_OpenID_ConsumerResponse {
    var $status = Auth_OpenID_SETUP_NEEDED;

    function Auth_OpenID_SetupNeededResponse($identity_url = null,
                                             $setup_url = null)
    {
        $this->identity_url = $identity_url;
        $this->setup_url = $setup_url;
    }
}

?>
