<?php
/***************************************************************************
*	filename	: 	user.php
*	desc.		:
*	Author		:	ericalens
*	created		:	mardi 6 juin 2006, 06:35:28 (UTC+0200)
*	modified	:	vendredi 9 juin 2006, 23:26:39 (UTC+0200)
***************************************************************************/

// Vérifié par dergonic le 8/12/16
// ! Authentification : Seule l'"internal" a été vérifiée !

if (!defined('IN_OGSMARKET')) {
	die("Hacking attempt");
}


//Gestion des utilisateurs
class cUsers {
	function get_user($user_id) {
		global $db;

		if (empty($user_id) || intval($user_id) != $user_id) {
			return false;
		}

		$sql = "SELECT * FROM ".TABLE_USER." WHERE id=$user_id limit 1";
		$db->sql_query($sql);
		return $db->sql_fetch_assoc();
	}

	function OnlineUsers($last_seen_seconds = 60) {
		global $db;

		$sql = "SELECT u.id, u.name FROM ".TABLE_USER." u, ".TABLE_SESSIONS." v WHERE v.id = u.id AND v.last_visit > '".(time() - $last_seen_seconds)."'";
		$db->sql_query($sql);
		$retval = Array();
		while ($data = $db->sql_fetch_assoc()) {
			$retval[] = $data;
		}
		return $retval;
	}

	function newaccount($password, $login, $repassword, $email, $pm_link, $irc_nick, $note, $alert_mail) {
		global $db;

		$sql = "SELECT value FROM ".TABLE_CONFIG." WHERE name='users_active'";
		$result = $db->sql_query($sql);
		list($active) = $db->sql_fetch_row($result);

		//manque des info là!
        if ($password == "" || $login == "" || $email == "") {
        	return "Il manque des informations !";
        }

		//ah non, mot de passe trop court.
		if (strlen($password) < 6) {
			return "Mot de passe inf&eacute;rieur à 6 caractères";
		}

		//erreur mot de passe.
		if ($password != $repassword) {
			return "erreur mot de passe";
		}

		$sql = "SELECT COUNT(*) FROM ".TABLE_USER." WHERE name like '".$db->sql_escape_string($login)."' OR (email like '".$db->sql_escape_string($email)."' AND email != '')";
		$db->sql_query($sql);
		// L'utilisateur existe.
		list($nb) = $db->sql_fetch_row();
		if ($nb != 0) {
			return "Nom ou email d'utilisateur déjà utilisé";
		}

		//Valeur de la checkbox
        $_alert_mail = $alert_mail;
        $alert_mail_ = 1;
        if (!isset($_alert_mail)) {
        	$alert_mail_ = 0;
        }

        //enregistrement.
        $sql = "INSERT INTO ".TABLE_USER." (name, password, regdate, lastvisit, email, pm_link, irc_nick, note, is_active, alert_mail) VALUES ('".$db->sql_escape_string($login)."', '".md5($password)."', ".time().", ".time()." , '".$db->sql_escape_string($email)."', '".$db->sql_escape_string($pm_link)."', '".$db->sql_escape_string($irc_nick)."', '".$db->sql_escape_string($note)."', '".$active."', '".$alert_mail_."')";
        $return = $db->sql_query($sql);
        if (!$return) {
        	return "Erreur lors de la création du compte";
        }
        return true;
	}

	function delete_account($user_id) {
		global $db;

		$db->sql_query("DELETE FROM ".TABLE_USER." WHERE id=".intval($user_id)." LIMIT 1");

		return "Le membre a bien &eacute;t&eacute; effac&eacute;";
	}

	function unset_active($user_id) {
		if (!empty($user_id)) {
			global $db;
			$db->sql_query("UPDATE ".TABLE_USER." SET "." is_active='0' "." WHERE id=".$user_id);
			return "Le membre a bien &eacute;t&eacute; D&eacute;sactiv&eacute;";
		}
}

	function set_active($user_id) {
		if (!empty($user_id)) {
			global $db;
			$db->sql_query("UPDATE ".TABLE_USER." SET "." is_active='1' "." WHERE id=".$user_id);
			return "Le membre a bien &eacute;t&eacute; Activ&eacute;";
		}
}
	function unset_admin($user_id) {
		if (!empty($user_id)) {
			global $db;
			$db->sql_query("UPDATE ".TABLE_USER." SET "." is_admin='0' "." WHERE id=".$user_id);
			return "Le membre a perdu son statut administrateur";
		}
}

	function set_admin($user_id) {
		if (!empty($user_id)) {
			global $db;
			$db->sql_query("UPDATE ".TABLE_USER." SET "." is_admin='1' "." WHERE id=".$user_id);
			return "Le membre a bien &eacute;t&eacute; nom&eacute; admin";
		}
}

	function unset_moderator($user_id) {
		if (!empty($user_id)) {
			global $db;
			$db->sql_query("UPDATE ".TABLE_USER." SET "." is_moderator='0' "." WHERE id=".$user_id);
			return "Le membre a perdu son status de mod&eacute;rateur";
		}
}

	function set_moderator($user_id) {
		if (!empty($user_id)) {
			global $db;
			$db->sql_query("UPDATE ".TABLE_USER." SET "." is_moderator='1' "." WHERE id=".$user_id);
			return "Le membre a bien &eacute;t&eacute; nom&eacute; mod&eacute;rateur";
		}
}


    /**
     * @param $form_username
     * @param $form_userpass
     * @return bool
     */
    function login($form_username, $form_userpass) {
		global $db;
		global $server_config;
		global $user_data;
		global $user_ip;

		switch ($server_config["users_auth_type"]) {
			//Utilisation du listing interne d'utilisateurs
			case "internal":
				$db->sql_query("SELECT id,is_active FROM ".TABLE_USER." WHERE name like '". $db->sql_escape_string($form_username)."'");
				// L'utilisateur existe pas
				if (!(list($id, $is_active) = $db->sql_fetch_row())) {
					return false;
				}
				if ($is_active != 1) {
					return false;
				}

				$sql = "SELECT * FROM ".TABLE_USER." WHERE id = '".$id."'";
				$db->sql_query($sql);
				$user = $db->sql_fetch_assoc();
				if ($user["password"] != md5($form_userpass)) {
					return false;
				}

				break;

			//Connection à partir de la liste utilisateurs de punbb
			case "punbb":
				$db_connect_id = mysqli_connect($server_config["users_adr_auth_db"], $server_config["users_auth_dbuser"], $server_config["users_auth_dbpassword"], true);
				if (!$db_connect_id) die("Impossible de se connecter &agrave; la base de donn&eacute;es. Contactez l\'Administrator");
				if (!mysqli_select_db($server_config["users_auth_db"])) {
					mysqli_close($db_connect_id);
					die("Impossible de se trouver la base de donn&eacute;es. Contactez l\'Administrator");
				}
				$sql = "SELECT password,email FROM ".$server_config["users_auth_table"]." WHERE username='".$db->sql_escape_string($form_username)."'";
				$result = mysqli_query($sql, $db_connect_id) or die(mysqli_error());
				list($db_password_hash, $db_email) = mysqli_fetch_row($result);

				$sha1_in_db = (strlen($db_password_hash) == 40) ? true : false;
				$sha1_available = (function_exists('sha1') || function_exists('mhash')) ? true : false;

				$form_password_hash = pun_hash($form_userpass); // This could result in either an SHA-1 or an MD5 hash (depends on $sha1_available)
				$autorized = false;
				if ($sha1_in_db && $sha1_available && $db_password_hash == $form_password_hash)
					$authorized = true;
				else if (!$sha1_in_db && $db_password_hash == md5($form_userpass))
				{
					$authorized = true;
				}
				if (!$authorized) return false;

				$sql = "SELECT id FROM ".TABLE_USER." WHERE name like '".$db->sql_escape_string($form_username)."'";
				$db->sql_query($sql);

				if (!(list($id) = $db->sql_fetch_row())) {
					//l'utilisateur n'est pas dans la base OGSMarket , on l'ajoute

					$sql = "INSERT INTO ".TABLE_USER." (name,email,regdate,lastvisit)"
					    ."VALUES('".$db->sql_escape_string($form_username)."','".$db_email."',"
					           ."'".time()."','".time()."')";
					$db->sql_query($sql);
					$id = $db->sql_insertid();

				}

				$sql = "SELECT * FROM ".TABLE_USER." WHERE id=$id";
				$db->sql_query($sql);
				$user = $db->sql_fetch_assoc();

				break;
			//Connection à partir de la liste utilisateurs de SMF Forum
			case "smf":
				$db_connect_id = mysqli_connect($server_config["users_adr_auth_db"], $server_config["users_auth_dbuser"], $server_config["users_auth_dbpassword"], true);
				if (!$db_connect_id) die("Impossible de se connecter &agrave; la base de donn&eacute;es. Contactez l\'Administrator");
				if (!mysqli_select_db($server_config["users_auth_db"])) {
					mysqli_close($db_connect_id);
					die("Impossible de se trouver la base de donn&eacute;es. Contactez l\'Administrator");
				}
				$sql = "SELECT passwd,emailAddress FROM ".$server_config["users_auth_table"]." WHERE memberName='".$db->sql_escape_string($form_username)."'";
				$result = mysqli_query($sql, $db_connect_id) or die(mysqli_error());
				list($db_password_hash, $db_email) = mysqli_fetch_row($result);

				$sha1_in_db = (strlen($db_password_hash) == 40) ? true : false;
				$sha1_available = (function_exists('sha1') || function_exists('mhash')) ? true : false;

				$form_password_hash = pun_hash($form_username.$form_userpass); // This could result in either an SHA-1 or an MD5 hash (depends on $sha1_available)
				$autorized = false;
				if ($sha1_in_db && $sha1_available && $db_password_hash == $form_password_hash)
					$authorized = true;
				else if (!$sha1_in_db && $db_password_hash == md5($form_userpass))
				{
					$authorized = true;
				}
				if (!$authorized) return false;


				$sql = "SELECT id FROM ".TABLE_USER." WHERE name like '".$db->sql_escape_string($form_username)."'";
				$db->sql_query($sql);

				if (!(list($id) = $db->sql_fetch_row())) {
					//l'utilisateur n'est pas dans la base OGSMarket , on l'ajoute

					$sql = "INSERT INTO ".TABLE_USER." (name,email,regdate,lastvisit)"
					    ."VALUES('".$db->sql_escape_string($form_username)."','".$db_email."',"
					           ."'".time()."','".time()."')";
					$db->sql_query($sql);
					$id = $db->sql_insertid();

				}

				$sql = "SELECT * FROM ".TABLE_USER." WHERE id=$id";
				$db->sql_query($sql);
				$user = $db->sql_fetch_assoc();

				break;

				//CONNECTION PHPBB2 by ChRom
			case "phpbb2":
				$db_connect_id = mysqli_connect($server_config["users_adr_auth_db"], $server_config["users_auth_dbuser"], $server_config["users_auth_dbpassword"], true);
				if (!$db_connect_id) die("Impossible de se connecter &agrave; la base de donn&eacute;es. Contactez l\'Administrator");
				if (!mysqli_select_db($server_config["users_auth_db"])) {
					mysqli_close($db_connect_id);
					die("Impossible de se trouver la base de donn&eacute;es. Contactez l\'Administrator");
				}
				$sql = "SELECT user_password,user_email FROM ".$server_config["users_auth_table"]." WHERE username='".sqli_escape_string($form_username)."'";
				$result = mysqli_query($sql, $db_connect_id) or die(mysqli_error());
				list($db_password_hash, $db_email) = mysqli_fetch_row($result);

				if ($db_password_hash != md5($form_userpass)) return false;

				$sql = "SELECT id FROM ".TABLE_USER." WHERE name like '".mysqli_escape_string($form_username)."'";
				sqli_query($sql);

				if (!(list($id) = $db->sqli_fetch_row())) {
					//l'utilisateur n'est pas dans la base OGSMarket , on l'ajoute

					$sql = "INSERT INTO ".TABLE_USER." (name,email,regdate,lastvisit)"
					    ."VALUES('".mysqli_escape_string($form_username)."','".$db_email."',"."'".time()."','".time()."')";
					$db->sql_query($sql);
					$id = $db->sql_insertid();

				}

				$sql = "SELECT * FROM ".TABLE_USER." WHERE id= '".$id."'";
				$db->sql_query($sql);
				$user = $db->sql_fetch_assoc();

			break;

			default:
				return false;
		}

		setcookie("ogsmarket_session", serialize(Array("name" => $form_username, "password" => md5($form_userpass), "validate" => (time() + 60*60*24*365))), (time() + 60*60*24*365));
		// Création ou mise à jour de la session serveur
		list($nb_id) = $db->sql_fetch_row($db->sql_query("SELECT COUNT(id) FROM ".TABLE_SESSIONS." WHERE id = '".$user["id"]."'"));
		if ($nb_id == 0) {
			$db->sql_query("INSERT INTO ".TABLE_SESSIONS." (`id`, `ip`, `last_connect`, `last_visit`) VALUES ('".$user["id"]."', '$user_ip', '".time()."', '".time()."')");
		}
		else {
			$db->sql_query("UPDATE ".TABLE_SESSIONS." u, ".TABLE_USER." v SET u.ip = '$user_ip', u.last_connect = '".time()."',  u.last_visit = '".time()."', v.countconnect = v.countconnect + 1 WHERE  u.id = v.id AND u.id = '".$user["id"]."'");
		}

		// Unserialize deliver informations
		$deliver = explode('_', $user["deliver"]);
		$user["deliver"] = Array();
		foreach ($deliver as $key=>$value) {
			$user["deliver"][$value] = 1;
		}
		for ($i = 1; $i <= 128; $i++) {
			if (!isset($user["deliver"][$i]) || $user["deliver"][$i] != 1) {
				$user["deliver"][$i] = 0;
			}
		}

		// Unserialize refunding informations
		$refunding = explode('_', $user["refunding"]);
		$user["refunding"] = Array();
		foreach ($refunding as $key=>$value) {
			$user["refunding"][$value] = 1;
		}
		for ($i = 1; $i <= 128; $i++) {
			if (!isset($user["refunding"][$i]) || $user["refunding"][$i] != 1) {
				$user["refunding"][$i] = 0;
			}
		}

		$user_data = $user;
		return $user_data;
	}

	function logout() {
		global $db;
		global $_COOKIE;
		global $user_data;

		$db->sql_query("DELETE FROM ".TABLE_SESSIONS." WHERE id = '".$user_data['id']."'");
		setcookie("ogsmarket_session", '', (time()));
		setcookie("ogsmarket_uni", '', (time()));
	}

	function profile_html($userid) {
		global $db;

		$user = $this->get_user($userid);
		if (!$user) {
			return "<div>Profil non trouvé</div>";
		}
	}

	function set_profile($email, $pm_link, $irc_nick, $avatar_link, $alert_mail, $skin, $note, $modepq, $deliver, $refunding) {
		global $db;
		global $user_data;

		$sql = "UPDATE ".TABLE_USER." SET "
			." email = '".$db->sql_escape_string($email)."' ,"
			." pm_link = '".$db->sql_escape_string($pm_link)."' ,"
			." irc_nick = '".$db->sql_escape_string($irc_nick)."', "
			." avatar_link = '".$db->sql_escape_string($avatar_link)."', "
			." alert_mail = '".intval($alert_mail)."', "
			." note = '".$db->sql_escape_string($note)."', "
			." skin = '".$db->sql_escape_string($skin)."', "
			." modepq = '".$db->sql_escape_string($modepq)."', "
			." deliver = '".implode('_', $deliver)."', "
			." refunding = '".implode('_', $refunding)."'"
			." WHERE id=".$user_data["id"];

		$db->sql_query($sql);
		redirection('index.php?action=profile');
	}

	function init_user() {
		global $db;
		global $_SERVER;
		global $_COOKIE;
		global $user_ip;

		if (isset($_COOKIE['ogsmarket_session'])) {
			$cookie = unserialize(stripslashes($_COOKIE['ogsmarket_session']));

			$user_data = $db->sql_fetch_assoc($db->sql_query("SELECT u.* FROM ".TABLE_USER." as u, ".TABLE_SESSIONS." as v WHERE v.ip = '$user_ip' AND u.name = '".$cookie['name']."' AND u.password = '".$cookie['password']."'"));

			if (isset($user_data['id'])) {
				$db->sql_query("UPDATE ".TABLE_USER." u, ".TABLE_SESSIONS." v SET u.lastvisit = '".time()."', v.last_visit = '".time()."' WHERE u.id = v.id AND u.id = '".$user_data['id']."'");
			}

			// Unserialize deliver information
			$deliver = explode('_', $user_data["deliver"]);
			$user_data["deliver"] = Array();
			foreach ($deliver as $key=>$value) {
				$user_data["deliver"][$value] = 1;
			}
			for ($i = 1; $i <= 128; $i++) {
				if (!isset($user_data["deliver"][$i]) || $user_data["deliver"][$i] != 1) {
					$user_data["deliver"][$i] = 0;
				}
			}

			// Unserialise refunding informations
            if( isset($user_data["refunding"])) {
                $refunding = explode('_', $user_data["refunding"]);
                $user_data["refunding"] = Array();
                foreach ($refunding as $key=>$value) {
                    $user_data["refunding"][$value] = 1;
                }
                for ($i = 1; $i <= 128; $i++) {
                    if (!isset($user_data["refunding"][$i]) || $user_data["refunding"][$i] != 1) {
                        $user_data["refunding"][$i] = 0;
                    }
                }
            }

			return $user_data;
		} else return false;
	}
}

function pun_hash($str)
{
	if (function_exists('sha1')) {	// Only in PHP 4.3.0+
		return sha1($str);
	}
	else if (function_exists('mhash')) {	// Only if Mhash library is loaded
		return bin2hex(mhash(MHASH_SHA1, $str));
	}
	else {
		return md5($str);
	}
}
$Users = new cUsers();
