<?php
/*
 * JobClass - Job Board Web Application
 * Copyright (c) BeDigit. All Rights Reserved
 *
 * Website: https://laraclassifier.com/jobclass
 *
 * LICENSE
 * -------
 * This software is furnished under a license and may be used and copied
 * only in accordance with the terms of such license and with the inclusion
 * of the above copyright notice. If you Purchased from CodeCanyon,
 * Please read the full License from here - http://codecanyon.net/licenses/standard
 */

namespace App\Observers;

use App\Helpers\Files\Storage\StorageDisk;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Resume;
use App\Models\SavedPost;
use App\Models\SavedSearch;
use App\Models\Scopes\ReviewedScope;
use App\Models\Scopes\VerifiedScope;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Notifications\UserActivated;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Notification;

class UserObserver
{
	/**
	 * Listen to the Entry created event.
	 *
	 * @param User $user
	 * @return void
	 */
	public function created(User $user)
	{
		// Send Admin Notification Email
		if (config('settings.mail.admin_notification') == '1') {
			try {
				// Get all admin users
				$admins = User::permission(Permission::getStaffPermissions())->get();
				if ($admins->count() > 0) {
					Notification::send($admins, new UserNotification($user));
				}
			} catch (\Throwable $e) {
			}
		}
	}
	
	/**
	 * Listen to the Entry deleting event.
	 *
	 * @param User $user
	 * @return void
	 */
	public function deleting(User $user)
	{
		// Storage Disk Init.
		$disk = StorageDisk::getDisk();
		
		// Delete the user's photo
		if (isset($user->photo) && !empty($user->photo)) {
			if ($disk->exists($user->photo)) {
				$disk->delete($user->photo);
			}
		}
		
		// Delete all user's Companies
		$companies = Company::where('user_id', $user->id);
		if ($companies->count() > 0) {
			foreach ($companies->cursor() as $company) {
				$company->delete();
			}
		}
		
		// Delete all user's Posts
		$posts = Post::withoutGlobalScopes([VerifiedScope::class, ReviewedScope::class])->where('user_id', $user->id);
		if ($posts->count() > 0) {
			foreach ($posts->cursor() as $post) {
				$post->delete();
			}
		}
		
		// Delete all user's Messages
		$messages = ThreadMessage::where('user_id', $user->id);
		if ($messages->count() > 0) {
			foreach ($messages->cursor() as $message) {
				$message->forceDelete();
			}
		}
		
		// Delete all user as Participant
		$participants = ThreadParticipant::where('user_id', $user->id);
		if ($participants->count() > 0) {
			foreach ($participants->cursor() as $participant) {
				$participant->forceDelete();
			}
		}
		
		// Delete all user's Saved Posts
		$savedPosts = SavedPost::where('user_id', $user->id);
		if ($savedPosts->count() > 0) {
			foreach ($savedPosts->cursor() as $savedPost) {
				$savedPost->delete();
			}
		}
		
		// Delete all user's Saved Searches
		$savedSearches = SavedSearch::where('user_id', $user->id);
		if ($savedSearches->count() > 0) {
			foreach ($savedSearches->cursor() as $savedSearch) {
				$savedSearch->delete();
			}
		}
		
		// Delete all user's Resumes
		$resumes = Resume::where('user_id', $user->id);
		if (!empty($resumes)) {
			foreach ($resumes->cursor() as $resume) {
				$resume->delete();
			}
		}
		
		// Removing Entries from the Cache
		$this->clearCache($user);
	}
	
	/**
	 * Listen to the Entry saved event.
	 *
	 * @param User $user
	 * @return void
	 */
	public function saved(User $user)
	{
		$this->sendNotification($user);
		
		// Create a new email token if the user's email is marked as unverified
		if (empty($user->email_verified_at)) {
			if (empty($user->email_token)) {
				$user->email_token = md5(microtime() . mt_rand());
				$user->save();
			}
		}
		
		// Create a new phone token if the user's phone number is marked as unverified
		if (empty($user->phone_verified_at)) {
			if (empty($user->phone_token)) {
				$user->phone_token = mt_rand(100000, 999999);
				$user->save();
			}
		}
		
		// Removing Entries from the Cache
		$this->clearCache($user);
	}
	
	/**
	 * Send Notification,
	 *
	 * - If the user's email address or phone number was not verified and has just been verified
	 *   (including when the user was recently created)
	 *
	 * @param User $user
	 * @return void
	 */
	private function sendNotification(User $user)
	{
		try {
			$userWasNotVerified = ($user->wasChanged('email_verified_at') || $user->wasChanged('phone_verified_at'));
			$userWasNotVerified = ($userWasNotVerified || $user->wasRecentlyCreated);
			$userIsVerified = (!empty($user->email_verified_at) && !empty($user->phone_verified_at));
			$userHasJustBeenVerified = ($userIsVerified && $userWasNotVerified);
			
			if ($userHasJustBeenVerified) {
				$user->notify(new UserActivated($user));
			}
		} catch (\Throwable $e) {
			abort(500, $e->getMessage());
		}
	}
	
	/**
	 * Removing the Entity's Entries from the Cache
	 *
	 * @param $user
	 */
	private function clearCache($user)
	{
		try {
			cache()->forget('count.users');
		} catch (\Exception $e) {
		}
	}
}
