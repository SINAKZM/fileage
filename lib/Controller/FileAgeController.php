<?php


namespace OCA\FileAge\Controller;


use OCA\FileAge\Service\FileAgeService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserSession;

class FileAgeController extends Controller {
	/**
	 * @var IUserSession
	 */
	private $userSession;
	/**
	 * @var FileAgeService
	 */
	private FileAgeService $fileAgeService;

	public function __construct(IUserSession $userSession, FileAgeService $fileAgeService) {
		$this->userSession = $userSession;
		$this->fileAgeService = $fileAgeService;
	}

	/**
	 * @param $age
	 * @param $fileInfo
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function submit($age, $fileInfo): JSONResponse {
		$selfCreatedFile = $this->fileAgeService->getSelfCreatedFile($this->userSession->getUser()->getUID(), $this->generateFileName($fileInfo));
		$share = $this->fileAgeService->getShareOwner($this->userSession->getUser()->getUID(), $this->getRootPath($fileInfo));
		if (!$share) {
			if ($selfCreatedFile){
				$this->fileAgeService->setExpiredAt($this->userSession->getUser()->getUID(), $this->generateFileName($fileInfo), $age);
				return new JSONResponse(
					[
						'result' => "delete request successfully submitted",
					]
				);
			}
			return new JSONResponse(
				[
					'error' => "not found",
				],400
			);
		}
		$this->fileAgeService->setExpiredAtOnShareOWner($this->userSession->getUser()->getUID(), $this->generateFileName($fileInfo), $age);
		return new JSONResponse(
			[
				'result' => "delete request successfully submitted",
			]
		);
	}
	/**
	 * @return JSONResponse
	 * @NoAdminRequired
	 */
	public function show($fileInfo): JSONResponse {
		$selfCreatedFile = $this->fileAgeService->getSelfCreatedFileOnShow($this->userSession->getUser()->getUID(), $this->generateFileName($fileInfo));
		return new JSONResponse(
			[
				'result' => $selfCreatedFile,

			]);
	}
	private function generateFileName($fileInfo): string {
		return !$fileInfo['dir'] ? "/{$fileInfo['name']}" : $fileInfo['dir'] . "/{$fileInfo['name']}";
	}
	private function getRootPath($fileInfo){
		return '/'.explode("/",$fileInfo['dir'])[1];
	}
}
