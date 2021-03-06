<?php
namespace Tx\Authcode;

/*                                                                        *
 * This script belongs to the TYPO3 Extension "authcode".                 *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Tx\Authcode\Domain\Enumeration\AuthCodeAction;
use Tx\Authcode\Domain\Enumeration\AuthCodeType;

/**
 * Provides methods for validating or invalidating auth codes.
 */
class AuthCodeValidator {

	protected $authCodeIsOptional = FALSE;

	/**
	 * @var \Tx\Authcode\Domain\Repository\AuthCodeRecordRepository
	 * @inject
	 */
	protected $authCodeRecordRepository;

	/**
	 * @var \Tx\Authcode\Domain\Repository\AuthCodeRepository
	 * @inject
	 */
	protected $authCodeRepository;

	/**
	 * @var \Tx\Authcode\Domain\Repository\AuthCodeSessionRepository
	 * @inject
	 */
	protected $authCodeSessionRepository;

	/**
	 * @var bool
	 */
	protected $forceRecordDeletion = FALSE;

	/**
	 * @var bool
	 */
	protected $invalidateAuthCodeAfterAccess = TRUE;

	/**
	 * @var bool
	 */
	protected $updateTimestampOnActivation = TRUE;

	/**
	 * Invalidates the submitted auth code
	 *
	 * @param \Tx\Authcode\Domain\Model\AuthCode $authCode
	 */
	public function invalidateAuthCode($authCode) {
		$this->authCodeSessionRepository->clearAuthCodeFromSession();
		$this->authCodeRepository->clearAssociatedAuthCodes($authCode);
	}

	/**
	 * @param boolean $authCodeIsOptional
	 */
	public function setAuthCodeIsOptional($authCodeIsOptional) {
		$this->authCodeIsOptional = (bool)$authCodeIsOptional;
	}

	/**
	 * @param boolean $forceRecordDeletion
	 */
	public function setForceRecordDeletion($forceRecordDeletion) {
		$this->forceRecordDeletion = $forceRecordDeletion;
	}

	/**
	 * @param bool $invalidateAuthCodeAfterAccess
	 */
	public function setInvalidateAuthCodeAfterAccess($invalidateAuthCodeAfterAccess) {
		$this->invalidateAuthCodeAfterAccess = (bool)$invalidateAuthCodeAfterAccess;
	}

	/**
	 * @param boolean $updateTimestampOnActivation
	 */
	public function setUpdateTimestampOnActivation($updateTimestampOnActivation) {
		$this->updateTimestampOnActivation = $updateTimestampOnActivation;
	}

	/**
	 * Checks the submitted auth code, executes the configured action and optionally
	 * redirects the user to a success page if the auth code is valid.
	 *
	 * If the auth code is invalid an exception will be thrown or the user will be
	 * redirected to a configured error page.
	 *
	 * @param \Tx\Authcode\Domain\Model\AuthCode|string|NULL $authCode The submitted auth code GET parameter, an auth code instance
	 * from the repository or NULL. If NULL, the auth code will be read from GET or the session.
	 * @throws Exception\InvalidAuthCodeException
	 * @return \Tx\Authcode\Domain\Model\AuthCode
	 */
	public function validateAuthCodeAndExecuteAction($authCode = NULL) {

		if (!isset($authCode)) {
			$authCode = $this->authCodeRepository->getSubmittedAuthCode();
		} elseif (is_string($authCode)) {
			$authCode = $this->authCodeRepository->findOneByAuthCode($authCode);
		}

		if (!isset($authCode) && !$this->authCodeIsOptional) {
			throw new \Tx\Authcode\Exception\InvalidAuthCodeException();
		}

		switch ($authCode->getType()) {

			// For independent records we do not need to load the auth code record data.
			case AuthCodeType::INDEPENDENT:
				break;

			// For record auth codes we check the action that should be executed for the record.
			case AuthCodeType::RECORD:

				switch ($authCode->getAction()) {

					// If action is enable record we unhide / enable the associated record.
					case AuthCodeAction::RECORD_ENABLE:
						$this->authCodeRecordRepository->enableAssociatedRecord($authCode, $this->updateTimestampOnActivation);
						break;

					// If action is delete record we delete the record.
					case AuthCodeAction::RECORD_DELETE:
						$this->authCodeRecordRepository->removeAssociatedRecord($authCode, $this->forceRecordDeletion);
						break;

					// For page access we do nothing
					case AuthCodeAction::ACCESS_PAGE:
						break;
				}
				break;
		}

		if ($this->invalidateAuthCodeAfterAccess) {
			$this->invalidateAuthCode($authCode);
		} else {
			// Store the authCode in the session so that the user can use it
			// on different pages without the need to append it as a get
			// parameter everytime
			$this->authCodeSessionRepository->storeAuthCodeInSession($authCode);
		}

		return $authCode;
	}
}