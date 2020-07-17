<?php
namespace App\Service;

use AGI_AsteriskManager;
use Exception;

class AsteriskApi {

	public static function cron() {
		$today = date('d-m-Y G:i:s');

		foreach(AsteriskAPI::getConfList() as $call) {
			if((strtotime($today) - strtotime(date($call['end']))) > (24 * 3600)) { //Conférence plus vielle de 1 jour
				AsteriskAPI::deleteConference($call['id']);
			}
		}
	}

	/**
	 * Delete a conference by his id
	 * @param $id string|int The conference ID to delete
	 */
	public static function deleteConference($id) {
		$asm = new AGI_AsteriskManager();

		if($asm->connect()) {
			$asm->command('database del conf ' . $id . '/creation');
			$asm->command('database del conf ' . $id . '/user');
			$asm->command('database del conf ' . $id . '/utilisation');
			$asm->command('database del conf ' . $id . '/pin');
			$asm->command('database del conf ' . $id . '/start');
			$asm->command('database del conf ' . $id . '/end');

			$asm->disconnect();
		}
	}

	/**
	 * @return int
	 */
	private static function r() {
		try {
			return random_int(1000, 9999);
		} catch (Exception $e) {
			return rand(1000, 9999);
		}

	}

	/**
	 * Create a conference
	 *
	 * @param $user string L'utilisateur
	 * @param $date string Date de la conférence
	 * @param $start string Heure de début de la conférence (heure:minute)
	 * @param $end string Heure de fin de la conférence (heure:minute)
	 * @since 1.0
	 * @author Romain Neil
	 * @return array
	 * @throws Exception
	 */
	public static function createConf($user, $date, $start, $end): array {
		$asm = new AGI_AsteriskManager();

		$conferences = array();

		$confList = AsteriskAPI::getConfList($asm);
		$confNumber = AsteriskAPI::r(); //1000-9999
		$confPin = AsteriskAPI::r(); //1000-9999

		for($i = 0; $i != sizeof($confList); $i++) {
			$conferences[$i] = $confList[$i]["id"];
		}

		while(in_array($confNumber, $conferences)) {
			$confNumber = random_int(1000, 9999);
			$confList = AsteriskAPI::getConfList();

			for($i = 0; $i != sizeof($confList); $i++) {
				$conferences[$i] = $confList[$i]["id"];
			}
		}

		if($asm->connect()) {
			$asm->command('database put conf ' . $confNumber . '/pin ' . $confPin);
			$asm->command('database put conf ' . $confNumber . '/creation "' . date('d-m-Y H:i') . '"');
			$asm->command('database put conf ' . $confNumber . '/start "' . date('d-m-Y H:i', strtotime($date . " " . $start)) . '"');
			$asm->command('database put conf ' . $confNumber . '/end "' . date('d-m-Y H:i', strtotime($date . " " . $end)) . '"');
			$asm->command('database put conf ' . $confNumber . '/user ' . $user);

			$asm->disconnect();
		}

		return array(
			"id" => $confNumber,
			"pin" => $confPin,
			"start" => $start,
			"end" => $end
		);

	}

	/**
	 * Récupère la liste des conférences en cours
	 *
	 * @since 1.0
	 * @author Romain Neil
	 * @return array
	 */
	public static function getCurrentConfs(): array {
		$asm = new AGI_AsteriskManager();
		$result = [];

		if($asm->connect()) {
			$cmd = $asm->command('confbridge list');
			$i = 0;
			$j = 0;

			foreach(explode("\n", $cmd["data"]) as $line) {
				$i++;

				if($i < 4) {
					//Ignore les 2 premières lignes
					continue;
				}

				if($line != "") {
					$ln = explode(" ", $line);

					$result[$j]["id"] = $ln[0];
					$result[$j]["nb"] = $ln[34];
				}

				$j++;
			}

			$asm->disconnect();
		}

		return $result;
	}

	/**
	 * Récupère les conférences
	 *
	 * @param AGI_AsteriskManager|null $asm
	 * @return array
	 */
	public static function getConfList($asm = null): array {
		if(is_null($asm)) {
			$asm = new AGI_AsteriskManager();
		}

		if($asm->connect())  {
			//$result = $asm->command("database show conf ".$_SERVER['REMOTE_USER']);
			$result = $asm->command("database show conf");

			if(!isset($result["data"])) {
				return [];
			} else {
				$confs = [];

				foreach(explode("\n", $result['data']) as $line) {
					if (preg_match("/conf/", $line)) {
						$pieces = explode("/", $line);

						$status = explode(" : ", $pieces[3]);

						$status[0] = trim($status[0]);

						$pieces[3] = $status;

						if(!isset($confs[$pieces[2]])) {
							$confs[$pieces[2]] = array();
							$confs[$pieces[2]]["id"] = $pieces[2];
						}

						$confs[$pieces[2]][$status[0]] = trim($status[1]);
					}
				}
				$asm->disconnect();
				sort($confs);

				return $confs;
			}
		}

		return [];
	}

}
