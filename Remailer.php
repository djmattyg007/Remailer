<?php

class Remailer
{
    const IS_REMAILED_HEADER = "X-Remailed";
    const IS_REMAILED = "true";
    const REMAILED_FROM_HEADER = "X-Remailed-From";

    /**
     * @var string
     */
    protected $emailContent = null;

    /**
     * @var callable
     */
    protected $parserFactory = null;

    /**
     * @var callable
     */
    protected $smtpFactory = null;

    /**
     * @var string[]
     */
    protected $whitelist = array();

    /**
     * @var string
     */
    protected $destHost = "localhost";

    /**
     * @var PlancakeEmailParser
     */
    protected $parser = null;

    /**
     * @param string $emailCnntent
     * @param callable $parserFactory
     * @param callable $smtpFactory
     * @param array $whitelist
     */
    public function __construct($emailContent, callable $parserFactory, callable $smtpFactory, $whitelist = array())
    {
        $this->setEmailContent($emailContent);
        $this->parserFactory = $parserFactory;
        $this->smtpFactory = $smtpFactory;
        $this->whitelist = $whitelist;
    }

    /**
     * @param string $emailContent
     */
    public function setEmailContent($emailContent)
    {
        $emailContent = $this->stripNonHeader($emailContent);
        $this->emailContent = $emailContent;
        $this->parser = null;
    }

    /**
     * @return string
     */
    public function getEmailContent()
    {
        return $this->emailContent;
    }

    /**
     * @param string[] $whitelist
     */
    public function setWhitelist(array $whitelist)
    {
        $this->whitelist = $whitelist;
    }

    /**
     * @param string $emailAddress
     * @return string
     */
    public function stripPlusAddress($emailAddress)
    {
        preg_match("#(.+)\+.+@(.+\..+)#", $emailAddress, $matches);
        if (empty($matches[1]) || empty($matches[2])) {
            return $emailAddress;
        }
        return $matches[1] . "@" . $matches[2];
    }

    /**
     * @param string $emailAddress
     * @return bool
     */
    public function isInWhitelist($emailAddress)
    {
        if (in_array($this->stripPlusAddress($emailAddress), $this->whitelist)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $host
     */
    public function setDestHost($host)
    {
        $this->destHost = $host;
    }

    /**
     * @return PlancakeEmailParser
     */
    public function getParser()
    {
        if ($this->parser === null) {
            $this->parser = call_user_func($this->parserFactory, $this->emailContent);
        }
        return $this->parser;
    }

    /**
     * @return Net_SMTP2
     */
    protected function getSmtpMailer()
    {
        $me = php_uname("n");
        $mailer = call_user_func($this->smtpFactory, $this->destHost, 25, $me);
        $mailer->setDebug(DEBUG);
        return $mailer;
    }

    /**
     * @return array|null
     */
    protected function getSender()
    {
        $sender = $this->getParser()->getSender();
        if (empty($sender)) {
            $from = $this->getParser()->getFrom();
            if (isset($from[0])) {
                $sender = $from[0];
            } else {
                return null;
            }
        }
        return $sender;
    }

    /**
     * @throws Exception
     */
    public function process()
    {
        if (empty($_SERVER["RECIPIENT"])) {
            throw new Exception("No recipient indicated.");
        }
        $recipient = $_SERVER["RECIPIENT"];
        if ($this->isInWhitelist($recipient) === false) {
            throw new Exception("No such persion at this address.");
        }

        if (!empty($_SERVER["SENDER"])) {
            $fromAddress = $_SERVER["SENDER"];
        } else {
            $sender = $this->getSender();
            if ($sender === null || empty($sender["email"])) {
                throw new Exception("No sender found.");
            }
            $fromAddress = $sender["email"];
        }

        if ($this->getParser()->getHeader(self::IS_REMAILED_HEADER) === self::IS_REMAILED) {
            throw new Exception("Redirect loop detected.");
        }

        $this->remail($recipient, $fromAddress);
    }

    /**
     * @param string $recipient
     * @param string $sender
     */
    protected function remail($recipient, $sender)
    {
        $headers = trim(strstr($this->emailContent, "\n\n", true));
        $message = substr(strstr($this->emailContent, "\n\n"), 2);
        $headers .= "\n" . self::IS_REMAILED_HEADER . ": " . self::IS_REMAILED;
        $headers .= "\n" . self::REMAILED_FROM_HEADER . ": $recipient";

        $mailer = $this->getSmtpMailer();
        $mailer->connect();
        $mailer->mailFrom($sender);
        $mailer->rcptTo($this->stripPlusAddress($recipient));
        $mailer->data($this->prepareLineEndings($message), $this->prepareLineEndings($headers));
        $mailer->disconnect();
    }

    /**
     * @param string $email
     * @return string
     */
    protected function stripNonHeader($emailContent)
    {
        $firstSpace = strpos($emailContent, " ");
        if ($firstSpace !== false) {
            $firstWord = substr($emailContent, 0, $firstSpace);
            if ($firstWord === "From") {
                $emailContent = substr($emailContent, strpos($emailContent, "\n") + 1);
            }
        }
        return $emailContent;
    }

    /**
     * @param string $text
     * @return string
     */
    protected function prepareLineEndings($text)
    {
        return str_replace("\n", "\r\n", $text);
    }
}
