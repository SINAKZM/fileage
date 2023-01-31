<?php

namespace OCA\FileAge\Service;

use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class FileAgeService {
	private $qb;
	private $dataDirectory;
	private \OCP\IUserManager $userManager;

	public function __construct(IDBConnection $db, Iconfig $config, \OCP\IUserManager $userManager) {
		$this->qb = $db->getQueryBuilder();
		$this->dataDirectory = $config->getSystemValue('datadirectory');
		$this->userManager = $userManager;
	}

	public function getExpiredFilesAndFolders() {
		$result = $this->qb->select('*')
			->from('activity')
			->where('expired_input IS NOT NULL ')
			->execute();
		return $result->fetchAll();
	}

	public function collectFilesAndFolders($expiredFilesAndFolders) {
		$data = ['files' => [], 'folders' => []];
		foreach ($expiredFilesAndFolders as $key => $row) {
			$absolutePath = $this->generateAbsolutePath($row['affecteduser'], $row['file']);
			if (is_dir($absolutePath)){
				$data['folders'][] = [
					'user' => $row['user'],
					'relativePath' => $row['file'],
					'absolutePath' => $absolutePath
				];
			}
		}
		return $data;
	}

	public function extractFilesFromFolders($folders) {
		$data = [];
		foreach ($folders as $folder) {
			$files = glob($folder['absolutePath'] . '/*'); // get all file names
			foreach ($files as $key => $file) {
				array_push($data,
					[
						'user' => $folder['user'],
						'relativePath' => $this->getRelativeFilePathFromFolder($folder['relativePath'], $file),
						'absolutePath' => $file,
						'folder'=>$folder['relativePath']
					]
				);
			}
		}
		return $data;
	}

	public function remove($files) {

		foreach ($files as $file) {
			if (is_file($file['absolutePath'])) {
				unlink($file['absolutePath']);
				$this->qb->update('activity')
					->set('removed_at', $this->qb->createNamedParameter(time()))
					->where($this->qb->expr()->eq('user', $this->qb->createNamedParameter($file['user'])))
					->andWhere($this->qb->expr()->eq('type', $this->qb->createNamedParameter('file_created')))
					->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($file['relativePath'])));
				$this->qb->execute();
			}
		}
	}

	public function removeExpired() {
		$filesAndFoldersCollection = $this->collectFilesAndFolders($this->getExpiredFilesAndFolders());
		$extractedFilesFromFolders = $this->extractFilesFromFolders($filesAndFoldersCollection['folders']);
		$expiredFiles = [];
		foreach ($extractedFilesFromFolders as $key=>$path){
			if (!is_dir($path['absolutePath'])){
				$fileActivity = $this->getFileActivity($path['relativePath']);
				$folderActivity = $this->getFolderActivity($path['folder']);
				$removeAtTimestamp = strtotime("+{$folderActivity['expired_input']} Days", $fileActivity['timestamp']);

				if ($removeAtTimestamp < time()){
					$expiredFiles[$key]= $path;
				}
			}
		}
		$this->remove($expiredFiles);
		$this->scan();
	}

	private function generateAbsolutePath($user, $fileOrFolderPath) {
		return $this->dataDirectory . '/' . $user . '/files' . $fileOrFolderPath;
	}

	private function getRelativeFilePathFromFolder($folderRelativePath, $absolutePath) {
		$explodedAbsolutePath = explode("/", $absolutePath);
		$filename = end($explodedAbsolutePath);
		return $folderRelativePath . '/' . $filename;
	}

	private function scan() {
		$users = $this->userManager->search('');
		foreach ($users as $user) {
			$user = $user->getUID();
			$scanner = new \OC\Files\Utils\Scanner(
				$user,
				new ConnectionAdapter($this->getConnection()),
				\OC::$server->query(IEventDispatcher::class),
				\OC::$server->get(LoggerInterface::class)
			);
			$scanner->scan("/{$user}", true, null);
		}
	}

	private function getConnection() {
		$connection = \OC::$server->get(Connection::class);
		try {
			$connection->close();
		} catch (\Exception $ex) {
		}
		while (!$connection->isConnected()) {
			try {
				$connection->connect();
			} catch (\Exception $ex) {
				sleep(60);
			}
		}
		return $connection;
	}

	public function getSelfCreatedFile($user, $path) {
		$result = $this->qb->select('*')
			->from('activity')
			->where($this->qb->expr()->eq('user', $this->qb->createNamedParameter($user)))
			->andWhere($this->qb->expr()->eq('subject', $this->qb->createNamedParameter('created_self')))
			->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($path)))
			->execute();
		return $result->fetch();
	}
	public function getSelfCreatedFileOnShow($user, $path) {
		$result = $this->qb->select('*')
			->from('activity')
			->where($this->qb->expr()->eq('affecteduser', $this->qb->createNamedParameter($user)))
			->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($path)))
			->execute();
		return $result->fetch();
	}
	public function getSelfCreatedPath($user, $path) {
		$result = $this->qb->select('*')
			->from('activity')
			->where($this->qb->expr()->eq('user', $this->qb->createNamedParameter($user)))
			->andWhere($this->qb->expr()->eq('type', $this->qb->createNamedParameter('self_created')))
			->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($path)))
			->execute();
		return $result->fetch();
	}

	public function setExpiredAt($user, $path, $input = null) {
		$this->qb->update('activity')
			->set('expired_input', $this->qb->createNamedParameter($input))
			->where($this->qb->expr()->eq('user', $this->qb->createNamedParameter($user)))
			->andWhere($this->qb->expr()->eq('type', $this->qb->createNamedParameter('file_created')))
			->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($path)));
		$this->qb->execute();
	}
	public function setExpiredAtOnShareOWner($user, $path, $input = null) {
		$this->qb->update('activity')
			->set('expired_input', $this->qb->createNamedParameter($input))
			->where($this->qb->expr()->eq('affecteduser', $this->qb->createNamedParameter($user)))
			->andWhere($this->qb->expr()->eq('type', $this->qb->createNamedParameter('file_created')))
			->andWhere($this->qb->expr()->eq('file', $this->qb->createNamedParameter($path)));
		$this->qb->execute();
	}

	public function getFolderActivity($folderPath) {
		return $this->qb->select('*')
			->from('activity')
			->where('file = :path')
			->setParameter("path", $folderPath)
			->execute()->fetch();
	}
	public function getFileActivity($filePath) {
		return $this->qb->select('*')
			->from('activity')
			->where('file = :path')
			->setParameter("path", $filePath)
			->execute()->fetch();
	}

	public function getShareOwner($user, $rootPath) {
		return $this->qb->select('*')
			->from('share')
			->where('file_target = :file_target')
			->andWhere('uid_owner = :uid_owner')
			->setParameter("file_target", $rootPath)
			->setParameter("uid_owner", $user)
			->execute()->fetch();
	}
}
