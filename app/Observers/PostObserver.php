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
use App\Models\Language;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Picture;
use App\Models\Post;
use App\Models\SavedPost;
use App\Models\Scopes\ActiveScope;
use App\Models\Scopes\StrictActiveScope;
use App\Models\Thread;
use App\Models\User;
use App\Notifications\PostActivated;
use App\Notifications\PostNotification;
use App\Notifications\PostReviewed;
use Illuminate\Support\Facades\Notification;

class PostObserver
{
	/**
	 * Listen to the Entry created event.
	 *
	 * @param Post $post
	 * @return void
	 */
	public function created(Post $post)
	{
		// Send Admin Notification Email
		if (config('settings.mail.admin_notification') == '1') {
			try {
				// Get all admin users
				$admins = User::permission(Permission::getStaffPermissions())->get();
				if ($admins->count() > 0) {
					Notification::send($admins, new PostNotification($post));
				}
			} catch (\Throwable $e) {
			}
		}
	}
	
	/**
	 * Listen to the Entry deleting event.
	 *
	 * @param Post $post
	 * @return void
	 */
	public function deleting(Post $post)
	{
		// Storage Disk Init.
		$disk = StorageDisk::getDisk();
		
		// Delete all Threads
		$messages = Thread::where('post_id', $post->id);
		if ($messages->count() > 0) {
			foreach ($messages->cursor() as $message) {
				$message->forceDelete();
			}
		}
		
		// Delete all Saved Posts
		$savedPosts = SavedPost::where('post_id', $post->id);
		if ($savedPosts->count() > 0) {
			foreach ($savedPosts->cursor() as $savedPost) {
				$savedPost->delete();
			}
		}
		
		// Remove logo files (if exists)
		if (empty($post->company_id)) {
			if (!empty($post->logo)) {
				$filename = str_replace('uploads/', '', $post->logo);
				if (
					!empty($filename)
					&& !str_contains($filename, config('larapen.core.picture.default'))
					&& $disk->exists($filename)
				) {
					$disk->delete($filename);
				}
			}
		}
		
		// Delete all Pictures
		$pictures = Picture::where('post_id', $post->id);
		if ($pictures->count() > 0) {
			foreach ($pictures->cursor() as $picture) {
				$picture->delete();
			}
		}
		
		// Delete the Payment(s) of this Post
		$payments = Payment::withoutGlobalScope(StrictActiveScope::class)->where('post_id', $post->id)->get();
		if ($payments->count() > 0) {
			foreach ($payments as $payment) {
				$payment->delete();
			}
		}
		
		// Remove the ad media folder
		if (!empty($post->country_code) && !empty($post->id)) {
			$directoryPath = 'files/' . strtolower($post->country_code) . '/' . $post->id;
			
			if ($disk->exists($directoryPath)) {
				$disk->deleteDirectory($directoryPath);
			}
		}
		
		// Removing Entries from the Cache
		$this->clearCache($post);
	}
	
	/**
	 * Listen to the Entry saved event.
	 *
	 * @param Post $post
	 * @return void
	 */
	public function saved(Post $post)
	{
		$this->sendNotification($post);
		
		// Create a new email token if the post's email is marked as unverified
		if (empty($post->email_verified_at)) {
			if (empty($post->email_token)) {
				$post->email_token = md5(microtime() . mt_rand());
				$post->save();
			}
		}
		
		// Create a new phone token if the post's phone number is marked as unverified
		if (empty($post->phone_verified_at)) {
			if (empty($post->phone_token)) {
				$post->phone_token = mt_rand(100000, 999999);
				$post->save();
			}
		}
		
		// Removing Entries from the Cache
		$this->clearCache($post);
	}
	
	/**
	 * Send Notification
	 *
	 * - If the user's email address or phone number was not verified and has just been verified
	 *   (including when the user was recently created)
	 * - If the listing was not reviewed and has just been reviewed
	 *  (including when the listing was recently created)
	 *
	 * @param Post $post
	 * @return void
	 */
	private function sendNotification(Post $post)
	{
		try {
			$postWasNotVerified = ($post->wasChanged('email_verified_at') || $post->wasChanged('phone_verified_at'));
			$postWasNotVerified = ($postWasNotVerified || $post->wasRecentlyCreated);
			$postIsVerified = (!empty($post->email_verified_at) && !empty($post->phone_verified_at));
			$postHasJustBeenVerified = ($postIsVerified && $postWasNotVerified);
			
			$postWasNotReviewed = ($post->wasChanged('reviewed_at'));
			$postWasNotReviewed = ($postWasNotReviewed || $post->wasRecentlyCreated);
			$postIsReviewed = (!empty($post->reviewed_at));
			$postHasJustBeenReviewed = ($postIsReviewed && $postWasNotReviewed);
			
			if ($postIsVerified) {
				if (config('settings.single.listings_review_activation') == '1') {
					if ($postHasJustBeenReviewed) {
						$post->notify(new PostReviewed($post));
					} else {
						if ($postHasJustBeenVerified) {
							$post->notify(new PostActivated($post));
						}
					}
				} else {
					if ($postHasJustBeenVerified) {
						$post->notify(new PostReviewed($post));
					}
				}
			}
		} catch (\Throwable $e) {
			abort(500, $e->getMessage());
		}
	}
	
	/**
	 * Listen to the Entry deleted event.
	 *
	 * @param Post $post
	 * @return void
	 */
	public function deleted(Post $post)
	{
		//...
	}
	
	/**
	 * Removing the Entity's Entries from the Cache
	 *
	 * @param $post
	 */
	private function clearCache($post)
	{
		try {
			cache()->forget($post->country_code . '.count.posts');
			
			cache()->forget($post->country_code . '.sitemaps.posts.xml');
			
			cache()->forget($post->country_code . '.home.getPosts.sponsored');
			cache()->forget($post->country_code . '.home.getPosts.latest');
			cache()->forget($post->country_code . '.home.getFeaturedPostsCompanies');
			
			cache()->forget('post.withoutGlobalScopes.with.city.pictures.' . $post->id);
			cache()->forget('post.with.city.pictures.' . $post->id);
			
			// Need to be caught (Independently)
			$languages = Language::withoutGlobalScopes([ActiveScope::class])->get(['abbr']);
			if ($languages->count() > 0) {
				foreach ($languages as $language) {
					cache()->forget('post.withoutGlobalScopes.with.city.pictures.' . $post->id . '.' . $language->abbr);
					cache()->forget('post.with.city.pictures.' . $post->id . '.' . $language->abbr);
					cache()->forget($post->country_code . '.count.posts.per.cat.' . $language->abbr);
				}
			}
			
			cache()->forget('posts.similar.category.' . $post->category_id . '.post.' . $post->id);
			cache()->forget('posts.similar.city.' . $post->city_id . '.post.' . $post->id);
		} catch (\Throwable $e) {
		}
	}
}
