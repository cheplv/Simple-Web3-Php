<?php

/**
 * This file is part of simple-web3-php package.
 * 
 * (c) Alex Cabrera  
 * 
 * @author Alex Cabrera
 * @license MIT 
 */

namespace SWeb3;
 

use SWeb3\Utils;
use Elliptic\EC;
use kornrunner\Keccak;
use stdClass;
use Exception;

 
class Account
{
	public string $privateKey;
	public string $publicKey;
	public string $address;

	public function sign(string $message)
	{ 
		$hash = Accounts::hashMessage($message);
		$signature = $this->signRaw($hash);
		$signature->message = $message;

		return $signature;
	}


	public function signRaw(string $hash)
	{ 
		//https://ethereum.stackexchange.com/questions/35425/web3-js-eth-sign-vs-eth-accounts-sign-producing-different-signatures
		$pk = $this->privateKey;
		if (substr($pk, 0, 2) != '0x') $pk  = '0x' . $pk;
	
		// 64 hex characters + hex-prefix
		if (strlen($pk) != 66) {
			throw new Exception("Private key must be length 64 + 2  (" . strlen($pk) . " provided)");
		}
			
		$ec = new EC('secp256k1'); 
		$ecPrivateKey = $ec->keyFromPrivate($pk, 'hex');  

		//https://ethereum.stackexchange.com/questions/86485/create-signed-message-without-json-rpc-node-in-php
		$signature = $ecPrivateKey->sign($hash, ['canonical' => true, "n" => null,]); 
		$r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
		$s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
		$v = dechex($signature->recoveryParam + 27);
 
		$res = new stdClass(); 
		$res->messageHash = '0x'.$hash;  
		$res->r = '0x'.$r; 
		$res->s = '0x'.$s; 
		$res->v = '0x'.$v; 
		$res->signature = '0x'.$r.$s.$v;//$signature; 

		return $res;

		//echo "Signed Hello world is:\n";
		//echo "Using my script:\n";
		//echo "0x$r$s$v\n";
		//echo "Using MEW:\n";
		//echo "0x2f52dfb196b75398b78c0e6c6aee8dc08d7279f2f88af5588ad7728f1e93dd0a479a710365c91ba649deb6c56e2e16836ffc5857cfd1130f159aebd05377d3a01c\n";

		//web3.eth.accounts.sign('Some data', '0x4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318');
		//> {
		//	message: 'Some data',
		//	messageHash: '0x1da44b586eb0729ff70a73c326926f6ed5a25f5b056e7f47fbc6e58d86871655',
		//	v: '0x1c',
		//	r: '0xb91467e570a6466aa9e9876cbcd013baba02900b8979d43fe208a4a4f339f5fd',
		//	s: '0x6007e74cd82e037b800186422fc2da167c747ef045e5d18a5f5d4300f8e1a029',
		///	signature: '0xb91467e570a6466aa9e9876cbcd013baba02900b8979d43fe208a4a4f339f5fd6007e74cd82e037b800186422fc2da167c747ef045e5d18a5f5d4300f8e1a0291c'
		//}
	}

}



class Accounts
{
 
	public static function create()
	{
		//Generates an account object with private key and public key.

		// Create the keypair
		$privateKey = Keccak::hash(Utils::GetRandomHex(128), 256);  
		
		return self::privateKeyToAccount($privateKey);
	} 


	public static function privateKeyToAccount(string $privateKey, bool $ignoreLength = false)
	{
		//Generates an account object with private key and public key.

		if (substr($privateKey, 0, 2) == '0x') {
			$privateKey = substr($privateKey, 2, strlen($privateKey) - 2);
		}
	 
		// 64 hex characters + hex-prefix
		if (!$ignoreLength && strlen($privateKey) !== 64) {
			throw new Exception("Private key must be 32 bytes long (" . (strlen($privateKey) / 2) . " provided)");
		}
		
		//get public key
		$ec = new EC('secp256k1');
		$ec_priv = $ec->keyFromPrivate($privateKey);
		$publicKey = $ec_priv->getPublic(true, "hex");
  
		// Returns a Web3 Account from a given privateKey 
		$account = new Account();
		$account->privateKey = '0x' . $privateKey;
		$account->publicKey = '0x' . $publicKey;
		$account->address = self::ecKeyToAddress($ec_priv->pub);

		return $account;
	}
  

	public static function hashMessage(string $message) : string
	{ 
		if (substr($message, 0, 2) == '0x') {
			$message  = substr($message, 2);
			if (ctype_xdigit($message) && strlen($message) % 2 == 0) {
				$message = hex2bin($message);
			} else {
				$message = '0x' . $message;
			}
		}

		$msglen = strlen($message);
		//"\x19Ethereum Signed Message:\n" + message.length + message and hashed using keccak256.
		$msg    = hex2bin("19") . "Ethereum Signed Message:" . hex2bin("0A") . $msglen . $message;
		$hash   = Keccak::hash($msg, 256);

		return $hash;

		//web3.eth.accounts.hashMessage("Hello World")
 		//"0xa1de988600a42c4b4ab089b619297c17d53cffae5d5120d82d8a92d0bb3b78f2" 
	}


	public static function ecKeyToAddress($pubEcKey) : string
	{
		return self::publicKeyToAddress($pubEcKey->encode("hex"));
	} 


	public static function publicKeyToAddress(string $pubkey) 
	{
		if (substr($pubkey, 0, 2) == '0x') $pubkey  = substr($pubkey, 2);
		return "0x" . substr(Keccak::hash(substr(hex2bin($pubkey), 1), 256), 24);
	} 


	public static function signedMessageToPublicKey(string $message, string $signature) : string
	{
		if (substr($message, 0, 2) == '0x') {
			$message  = substr($message, 2);
			if (ctype_xdigit($message) && strlen($message) % 2 == 0) {
				$message = hex2bin($message);
			} else {
				$message = '0x' . $message;
			}
		}


		$msglen = strlen($message);
		$hash   = Keccak::hash("\x19Ethereum Signed Message:\n{$msglen}{$message}", 256);
		$sign   = ["r" => substr($signature, 2, 64), 
				   "s" => substr($signature, 66, 64)];
		$recid  = ord(hex2bin(substr($signature, 130, 2))) - 27; 
		if ($recid != ($recid & 1)) 
			return false;
	
		$ec = new EC('secp256k1');
		$pubEcKey = $ec->recoverPubKey($hash, $sign, $recid);
	
		return $pubEcKey->encode("hex");
	}


	public static function verifySignatureWithPublicKey(string $message, string $signature, string $publicKey) : bool
	{ 
		return $publicKey == self::signedMessageToPublicKey($message, $signature);
	}


	public static function verifySignatureWithAddress(string $message, string $signature, string $address) : bool
	{
		$publicKey = self::signedMessageToPublicKey($message, $signature);
		$message_address = self::publicKeyToAddress($publicKey);

		return $address == $message_address;
	}
	
}