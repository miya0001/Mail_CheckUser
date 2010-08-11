<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * check email addresses for validity with SMTP commands
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category Mail
 * @package  Mail_CheckUser
 * @author   Takayuki Miyauchi <miya@theta.ne.jp>
 * @license  http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link     http://firegoby.theta.ne.jp/
 */

require_once 'PEAR.php';
require_once 'Mail/RFC822.php';
require_once 'Net/SMTP.php';

/**
 * check email addresses for validity with SMTP commands
 *
 * @category Mail
 * @package  Mail_CheckUser
 * @author   Takayuki Miyauchi <miya@theta.ne.jp>
 * @license  http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link     http://firegoby.theta.ne.jp/
 */
class Mail_CheckUser
{
    /**
     * Email address to use RCPT TO
     *
     * Email address to use RCPT TO. Default $env['SERVER_ADMIN']
     *
     * @var string
     * @access public
     */
    protected $sender = null;

    /**
     * Your server's FQDN to use EHLO(HELO)
     *
     * Server FQDN to use EHLO or HELO. Default $env['SERVER_NAME']
     *
     * @var string
     * @access public
     */
    protected $fqdn = null;

    /**
     * SMTP(ESMTP) response.
     *
     * checkEmail() set value of SMTP response message to this variable
     *
     * @var string
     * @access public
     */
    protected $response = null;

    /**
     * SMTP(ESMTP) response code.
     *
     * checkEmail() set value of SMTP response code to this variable
     *
     * @var int
     * @access public
     */
    protected $response_code = null;

    /**
     * Custom response codes.
     *
     * When the response code is not returned from SMTP server,
     * either of these arrays is returned. 
     *
     * @var array
     * @access protected
     */
    protected $err_codes = array(
        1000 => 'Bad syntax',
        1001 => 'Connection failed',
        1002 => 'Mail server not found',
    );

    /**
     * PEAR_Error Object
     *
     * @var object
     * @access protected
     */
    protected $error = null;

    /**
     * You can modify this values by set*() methods
     *
     * @var array
     * @access protected
     */
    protected $data = null;

    /**
     * Instantiates a new Mail_CheckUser object.
     *
     * @param string $fqdn   The string of your server's FQDN
     * @param string $sender The string of your Sender address
     *
     * @access public
     * @since 0.1.0
     */
    function __construct($fqdn = null, $sender = null)
    {
        $sapi_type = php_sapi_name();
        if (substr($sapi_type, 0, 3) == 'cgi') {
            $env = $_ENV;
        } else {
            $env = $_SERVER;
        }

        if ($fqdn) {
            $this->fqdn = $fqdn;
        } elseif (isset($env['HOSTNAME']) && $env['HOSTNAME']) {
            $this->fqdn = $env['HOSTNAME'];
        } else {
            $this->fqdn = $env['SERVER_NAME'];
        }
        if ($sender) {
            $this->sender = $sender;
        } elseif (isset($env['USER']) && $env['USER']) {
            $this->sender = $env['USER'].'@'.$this->fqdn;
        } else {
            $this->sender = $env['SERVER_ADMIN'];
        }

        $this->data['timeout']  = 5;
        $this->data['ok_codes'] = array(
            250,
            251,
        );
    }

    /**
     * Check email address for validity with SMTP commands
     *
     * @param string $email The string of email address  to check
     *
     * @return bool True on succes
     *
     * @access public
     * @since 0.1.0
     */
    public function checkEmail( $email )
    {
        $pear        = new PEAR();
        $Mail_RFC822 = new Mail_RFC822();

        $email = trim($email);
        if ( $m = $Mail_RFC822->isValidInetAddress($email) ) {
            $user = $m[0];
            $host = $m[1];
        } else {
            $this->setResponse(1000, $this->err_codes[1000]);
            $pear->raiseError($this->err_codes[1000], 1000);
            return false;
        }
        $result = getmxrr($host, $servers);
        if ( !$result ) {
            if ( gethostbynamel($host) ) {
                $servers[] = $host;
            } else {
                $this->setResponse(1002, $this->err_codes[1002]);
                $pear->raiseError($this->err_codes[1002], 1002);
                return $this->result();
            }
        }
        foreach ( $servers as $server ) {
            $smtp = new Net_SMTP($server, 25, $this->fqdn);
            if ($pear->isError($error = $smtp->connect($this->data['timeout']))) {
                $this->setResponse(1001, $error->getMessage());
                $this->error = $error;
                continue;
            }
            if ($pear->isError($error = $smtp->mailFrom($this->sender))) {
                $this->error = $error;
                $res         = $smtp->getResponse();
                if ($res[0] == -1) {
                    $this->setResponse(1001, $this->err_codes[1001]);
                    continue;
                } else {
                    $this->setResponse($res[0], $res[1]);
                    return $this->result();
                }
            }
            if ($pear->isError($error = $smtp->rcptTo($email))) {
                $this->error = $error;
            }
            $res                 = $smtp->getResponse();
            $this->response_code = $res[0];
            $this->response      = $res[1];
            $smtp->disconnect();
            if ( $res[0] == -1 ) {
                continue;
            } else {
                return $this->result();
            }
        } // end foreach

        return $this->result();
    } // end checkEmail()

    /**
     * Set seconds for timeout when SMTP connect
     *
     * @param int $timeout Seconds for timeout
     *
     * @return  object  This object
     *
     * @access public
     * @since 0.1.1
     */
    public function setTimeout($timeout)
    {
        $this->data['timeout'] = (int) $timeout;
        return $this;
    }

    /**
     * Return PEAR_Error object
     *
     * @return  object  PEAR_Error object
     *
     * @access public
     * @since 0.1.1
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set response code for return true
     *
     * @param mixed $codes unlimited number of response codes to return true
     *
     * @return object This object
     *
     * @access public
     * @since 0.1.1
     */
    public function setOKCodes($codes)
    {
        $args     = func_get_args();
        $ok_codes = $this->data['ok_codes'];
        $ok_codes = array_merge($ok_codes, $args);
        $ok_codes = array_unique($ok_codes);

        $this->data['ok_codes'] = $ok_codes;
        return $this;
    }

    /**
     * Return SMTP response code and response as array
     *
     * @return  array  response code and response message
     *
     * @access public
     * @since 0.1.0
     */
    public function getResponse()
    {
        return array($this->response_code, $this->response);
    }


    /**
     * Return true if SMTP response will match ok_codes
     * Its will call from checkEmail()
     *
     * @return  bool            True or false
     *
     * @access protected
     * @since 0.1.0
     */
    protected function result()
    {
        if ( $this->response_code == -1 ) {
            $this->response      = $this->err_codes[1001];
            $this->response_code = 1001;
        }
        if ( in_array($this->response_code, $this->data['ok_codes']) ) {
            $this->error = null;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set response code when SMTP server was not returned
     * Its will call from checkEmail()
     *
     * @param int    $code custom response code
     * @param string $msg  custom response message
     *
     * @return  bool           True or false
     *
     * @access protected
     * @since 0.1.1
     */
    protected function setResponse($code, $msg)
    {
        $this->response_code = $code;
        $this->response      = $msg;
        return $this;
    }

}

?>
