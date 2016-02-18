<?php
/**
 * (c) Spryker Systems GmbH copyright protected
 */

namespace Spryker\Zed\Auth\Business\Model;

use Generated\Shared\Transfer\UserTransfer;
use Spryker\Zed\Auth\Dependency\Facade\AuthToUserBridge;
use Spryker\Zed\Auth\Dependency\Plugin\AuthPasswordResetSenderInterface;
use Spryker\Zed\Auth\Persistence\AuthQueryContainer;
use Orm\Zed\Auth\Persistence\SpyResetPassword;
use Orm\Zed\Auth\Persistence\Map\SpyResetPasswordTableMap;
use Spryker\Zed\Auth\AuthConfig;

class PasswordReset
{

    const LENGTH = 22;

    /**
     * @var \Spryker\Zed\Auth\Persistence\AuthQueryContainer
     */
    protected $authQueryContainer;

    /**
     * @var \Spryker\Zed\Auth\Dependency\Plugin\AuthPasswordResetSenderInterface
     */
    protected $userPasswordResetNotificationSender;

    /**
     * @var \Spryker\Zed\Auth\Dependency\Facade\AuthToUserBridge
     */
    protected $facadeUser;

    /**
     * @var \Spryker\Zed\Auth\AuthConfig
     */
    protected $authConfig;

    /**
     * @param \Spryker\Zed\Auth\Persistence\AuthQueryContainer $authQueryContainer
     * @param \Spryker\Zed\Auth\Dependency\Facade\AuthToUserBridge $facadeUser
     * @param \Spryker\Zed\Auth\AuthConfig $authConfig
     */
    public function __construct(
        AuthQueryContainer $authQueryContainer,
        AuthToUserBridge $facadeUser,
        AuthConfig $authConfig
    ) {
        $this->authQueryContainer = $authQueryContainer;
        $this->facadeUser = $facadeUser;
        $this->authConfig = $authConfig;
    }

    /**
     * @param string $email
     *
     * @return bool
     */
    public function requestToken($email)
    {
        $userTransfer = $this->facadeUser->getUserByUsername($email);

        if (empty($userTransfer)) {
            return false;
        }

        $passwordResetToken = $this->generateToken();

        $result = $this->persistResetPassword($passwordResetToken, $userTransfer);

        $this->sendResetRequest($email, $passwordResetToken);

        return $result;
    }

    /**
     * @param string $passwordResetToken
     * @param \Generated\Shared\Transfer\UserTransfer $userTransfer
     *
     * @throws \Propel\Runtime\Exception\PropelException
     *
     * @return bool
     */
    protected function persistResetPassword($passwordResetToken, UserTransfer $userTransfer)
    {
        $resetPasswordEntity = new SpyResetPassword();
        $resetPasswordEntity->setCode($passwordResetToken);
        $resetPasswordEntity->setFkUser($userTransfer->getIdUser());
        $resetPasswordEntity->setStatus(SpyResetPasswordTableMap::COL_STATUS_ACTIVE);

        $affectedRows = $resetPasswordEntity->save();

        return $affectedRows > 0;
    }

    /**
     * @param string $token
     * @param string $newPassword
     *
     * @return bool
     */
    public function resetPassword($token, $newPassword)
    {
        $resetPasswordEntity = $this->authQueryContainer->queryForActiveCode($token)->findOne();

        if (empty($resetPasswordEntity)) {
            return false;
        }

        $userTransfer = $this->facadeUser->getUserById($resetPasswordEntity->getFkUser());
        $userTransfer->setPassword($newPassword);
        $this->facadeUser->updateUser($userTransfer);

        $resetPasswordEntity->setStatus(SpyResetPasswordTableMap::COL_STATUS_USED);
        $affectedRows = $resetPasswordEntity->save();

        return $affectedRows > 0;
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isValidToken($token)
    {
        $resetPasswordEntity = $this->authQueryContainer->queryForActiveCode($token)->findOne();

        if (empty($resetPasswordEntity)) {
            return false;
        }

        $expiresInSeconds = $this->authConfig->getPasswordTokenExpirationInSeconds();
        $expiresAt = $resetPasswordEntity->getCreatedAt();
        $expiresAt->add(new \DateInterval('PT' . $expiresInSeconds . 'S'));

        $currentDateTime = new \DateTime();

        if ($currentDateTime > $expiresAt) {
            $resetPasswordEntity->setStatus(SpyResetPasswordTableMap::COL_STATUS_EXPIRED);
            $resetPasswordEntity->save();

            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    protected function generateToken()
    {
        $function = 'openssl_random_pseudo_bytes';
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $function = 'random_bytes';
        }

        $length = self::LENGTH / 2;
        $token = bin2hex(call_user_func($function, (int)$length));

        if (strlen($token) !== self::LENGTH) {
            $token = str_pad($token, self::LENGTH, '0');
        }

        return $token;
    }

    /**
     * @param string $email
     * @param string $passwordResetToken
     *
     * @return void
     */
    protected function sendResetRequest($email, $passwordResetToken)
    {
        if ($this->userPasswordResetNotificationSender !== null) {
            $this->userPasswordResetNotificationSender->send($email, $passwordResetToken);
        }
    }

    /**
     * @param \Spryker\Zed\Auth\Dependency\Plugin\AuthPasswordResetSenderInterface $userPasswordResetNotificationSender
     *
     * @return void
     */
    public function setUserPasswordResetNotificationSender(
        AuthPasswordResetSenderInterface $userPasswordResetNotificationSender
    ) {
        $this->userPasswordResetNotificationSender = $userPasswordResetNotificationSender;
    }

}
