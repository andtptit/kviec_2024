<?php
/**
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

namespace App\Http\Controllers\Api;

use App\Helpers\Arr;
use App\Helpers\Files\Upload;
use App\Http\Controllers\Api\Auth\Traits\CompleteMissingAuthDataTrait;
use App\Http\Controllers\Api\Thread\UpdateByTypeTrait;
use App\Http\Requests\ReplyMessageRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\EntityCollection;
use App\Http\Resources\PostResource;
use App\Http\Resources\ThreadResource;
use App\Models\Post;
use App\Models\Resume;
use App\Models\Thread;
use App\Models\ThreadMessage;
use App\Models\ThreadParticipant;
use App\Models\User;
use App\Notifications\ReplySent;
use App\Notifications\EmployerContacted;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;

/**
 * @group Threads
 */
class ThreadController extends BaseController
{
	use CompleteMissingAuthDataTrait;
	use UpdateByTypeTrait;
	
	/**
	 * List threads
	 *
	 * Get all logged user's threads
	 * Filters:
	 * - unread: Get the logged user's unread threads
	 * - started: Get the logged user's started threads
	 * - important: Get the logged user's make as important threads
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @queryParam filter string Filter for the list - Possible value: unread, started or important. Example: unread
	 * @queryParam embed string Comma-separated list of the post relationships for Eager Loading - Possible values: post. Example: null
	 * @queryParam perPage int Items per page. Can be defined globally from the admin settings. Cannot be exceeded 100. Example: 2
	 *
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function index()
	{
		$user = auth('sanctum')->user();
		
		// All threads
		$threads = Thread::whereHas('post', function ($query) {
			$query->currentCountry()->unarchived();
		});
		
		$embed = explode(',', request()->get('embed'));
		
		if (in_array('post', $embed)) {
			$threads->with('post');
		}
		
		if (request()->get('filter') == 'unread') {
			// Get threads that have new messages or that are marked as unread
			$threads->forUserWithNewMessages($user->id);
		} else {
			// Get threads that user is participating in
			$threads->forUser($user->id)->latest('updated_at');
		}
		
		// Get threads started by this user
		if (request()->get('filter') == 'started') {
			$threadTable = (new Thread())->getTable();
			$messageTable = (new ThreadMessage())->getTable();
			
			$threads->where(function ($query) use ($threadTable, $messageTable) {
				$query->select('user_id')
					->from($messageTable)
					->whereColumn($messageTable . '.thread_id', $threadTable . '.id')
					->orderBy($messageTable . '.created_at', 'ASC')
					->limit(1);
			}, $user->id);
		}
		
		// Get this user's important thread
		if (request()->get('filter') == 'important') {
			$threads->where('is_important', 1);
		}
		
		// Get rows & paginate
		$threads = $threads->paginate($this->perPage);
		
		// If the request is made from the app's Web environment,
		// use the Web URL as the pagination's base URL
		$threads = setPaginationBaseUrl($threads);
		
		$collection = new EntityCollection(class_basename($this), $threads);
		
		$message = ($threads->count() <= 0) ? t('no_threads_found') : null;
		
		return $this->respondWithCollection($collection, $message);
	}
	
	/**
	 * Get thread
	 *
	 * Get a thread (owned by the logged user) details
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @queryParam embed string Comma-separated list of the post relationships for Eager Loading - Possible values: user,post,messages,participants. Example: null
	 *
	 * @urlParam id int required The thread ID. Example: 2
	 *
	 * @param $id
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function show($id)
	{
		$user = auth('sanctum')->user();
		
		$thread = Thread::query();
		
		$embed = explode(',', request()->get('embed'));
		
		if (in_array('user', $embed)) {
			// See the ThreadResource
		}
		
		if (in_array('post', $embed)) {
			$thread->with('post');
		}
		
		// Call the ThreadMessageController endpoint to get paginated messages
		if (in_array('messages', $embed)) {
			// See the ThreadResource
		}
		
		if (in_array('participants', $embed)) {
			$thread->with('users');
		}
		
		$threadTable = (new Thread())->getTable();
		$thread->forUser($user->id)->where($threadTable . '.id', $id);
		
		$thread = $thread->first();
		
		abort_if(empty($thread), 404, t('thread_not_found'));
		
		// Mark the Thread as read
		$thread->markTheThreadAsRead($user->id);
		
		$resource = new ThreadResource($thread);
		
		return $this->respondWithResource($resource);
	}
	
	/**
	 * Store thread
	 *
	 * Start a conversation. Creation of a new thread.
	 *
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @bodyParam post_id int required The related post ID. Example: 2
	 * @bodyParam name string required The thread's creator name. Example: John Doe
	 * @bodyParam auth_field string required The user's auth field ('email' or 'phone'). Example: email
	 * @bodyParam email string The thread's creator email address (Required when 'auth_field' value is 'email'). Example: john.doe@domain.tld
	 * @bodyParam phone string The thread's creator mobile phone number (Required when 'auth_field' value is 'phone').
	 * @bodyParam phone_country string required The user's phone number's country code (Required when the 'phone' field is filled). Example: null
	 * @bodyParam body string required The name of the user. Example: Modi temporibus voluptas expedita voluptatibus voluptas veniam.
	 * @bodyParam filename file The thread attached file.
	 * @bodyParam captcha_key string Key generated by the CAPTCHA endpoint calling (Required when the CAPTCHA verification is enabled from the Admin panel).
	 *
	 * @param \App\Http\Requests\SendMessageRequest $request
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function store(SendMessageRequest $request)
	{
		if (!$request->filled('post_id')) {
			$msg = 'The "post_id" parameter is required.';
			
			return $this->respondError($msg);
		}
		
		$user = null;
		if (auth('sanctum')->check()) {
			$user = auth('sanctum')->user();
		}
		
		// Get the Post
		$post = Post::unarchived()->findOrFail($request->input('post_id'));
		
		// Check and complete missing auth data
		$missingAuthDataCompleted = $this->completeMissingAuthData();
		
		// Get or Create Resume
		if (!empty($user) && $request->filled('resume_id') && !empty($request->input('resume_id'))) {
			// Get the User's Resume
			$resume = Resume::where('id', $request->input('resume_id'))->where('user_id', $user->id)->first();
		} else {
			// Get Resume Input
			$resumeInput = $request->input('resume');
			$resumeInput['active'] = 1;
			if (!isset($resumeInput['filename'])) {
				$resumeInput['filename'] = null;
			}
			if (empty($resumeInput['country_code'])) {
				$resumeInput['country_code'] = config('country.code');
			}
			
			// Logged Users
			if (!empty($user)) {
				if (empty($resumeInput['user_id'])) {
					$resumeInput['user_id'] = $user->id;
				}
				
				// Store the User's Resume
				$resume = new Resume($resumeInput);
				$resume->save();
				
				// Save the Resume's file
				if ($request->hasFile('resume.filename')) {
					// Upload File
					$destPath = 'resumes/' . strtolower($resume->country_code) . '/' . $resume->user_id;
					$resume->filename = Upload::file($destPath, $request->file('resume.filename'), 'private');
					
					$resume->save();
				}
			} else {
				// Guest Users
				$resume = Arr::toObject($resumeInput);
			}
		}
		
		// Return error if resume is not set
		if (empty($resume)) {
			$msg = t('Please select a resume or New Resume to add one');
			
			return $this->respondError($msg);
		}
		
		// Create Message Array
		$messageArray = $request->all();
		
		// Logged User
		if (!empty($user) && !empty($post->user)) {
			// Thread
			$thread = new Thread();
			$thread->post_id = $post->id;
			$thread->subject = $post->title;
			$thread->save();
			
			// Message
			$message = new ThreadMessage();
			$message->thread_id = $thread->id;
			$message->user_id = $user->id;
			$message->body = $request->input('body');
			$message->filename = $resume->filename;
			$message->save();
			
			// Update Message Array
			$messageArray['name'] = $user->name;
			$messageArray['email'] = $user->email;
			$messageArray['phone'] = $user->phone;
			$messageArray['country_code'] = $post->country_code ?? config('country.code');
			if (!empty($message->filename)) {
				$messageArray['filename'] = $message->filename;
			}
			
			// Sender
			$sender = new ThreadParticipant();
			$sender->thread_id = $thread->id;
			$sender->user_id = $user->id;
			$sender->last_read = new Carbon();
			$sender->save();
			
			// Recipients
			if ($request->has('recipients')) {
				$thread->addParticipant($request->input('recipients'));
			} else {
				$thread->addParticipant($post->user->id);
			}
		} else {
			// Guest (Non Logged User)
			// Update the filename
			if ($request->hasFile('resume.filename')) {
				$file = $request->file('resume.filename');
				$messageArray['filename'] = $file->getClientOriginalName();
				$messageArray['fileData'] = base64_encode(File::get($file->getRealPath()));
			}
		}
		
		// Remove input file to prevent Laravel Queue serialization issue
		if (isset($messageArray['resume']) && !is_string($messageArray['resume'])) {
			unset($messageArray['resume']);
		}
		if (isset($messageArray['filename']) && !is_string($messageArray['filename'])) {
			unset($messageArray['filename']);
		}
		
		// Send a message to publisher
		if (isset($messageArray['post_id'], $messageArray['email'], $messageArray['name'], $messageArray['body'])) {
			try {
				$post->notify(new EmployerContacted($post, $messageArray));
			} catch (\Throwable $e) {
				return $this->respondInternalError($e->getMessage());
			}
		}
		
		$msg = t('message_has_sent_successfully_to', ['contact_name' => $post->contact_name]);
		
		$data = [
			'success' => true,
			'message' => $msg,
		];
		
		if (isset($thread) && !empty($thread)) {
			$data['result'] = (new ThreadResource($thread))->toArray($request);
		} else {
			$data['result'] = null;
		}
		
		$extra = [];
		
		$extra['post'] = (new PostResource($post))->toArray($request);
		$extra['missingAuthDataCompleted'] = $missingAuthDataCompleted;
		
		$data['extra'] = $extra;
		
		return $this->respondCreated($data);
	}
	
	/**
	 * Update thread
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @bodyParam body string required The name of the user. Example: Modi temporibus voluptas expedita voluptatibus voluptas veniam.
	 * @bodyParam filename file The thread attached file.
	 *
	 * @param $id
	 * @param \App\Http\Requests\ReplyMessageRequest $request
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	public function update($id, ReplyMessageRequest $request)
	{
		$user = auth('sanctum')->user();
		
		try {
			// We use with([users => fn()]) to prevent email sending
			// to the message sender (which is the current user)
			$thread = Thread::with([
				'post',
				'users' => function ($query) use ($user) {
					$query->where((new User())->getTable() . '.id', '!=', $user->id);
				},
			])->findOrFail($id);
		} catch (ModelNotFoundException $e) {
			return $this->respondNotFound(t('thread_not_found', ['id' => $id]));
		}
		
		// Re-activate the Thread for all participants
		$thread->deleted_by = null;
		$thread->save();
		
		$thread->activateAllParticipants();
		
		// Create Message Array
		$messageArray = $request->all();
		
		// Message
		$message = new ThreadMessage();
		$message->thread_id = $thread->id;
		$message->user_id = $user->id;
		$message->body = $request->input('body');
		$message->save();
		
		// Save and Send user's resume
		if ($request->hasFile('filename')) {
			// Upload File
			if (!empty($thread->post)) {
				$post = $thread->post;
				$destPath = 'files/' . strtolower($post->country_code) . '/' . $post->id . '/applications';
				$message->filename = Upload::file($destPath, $request->file('filename'));
				
				$message->save();
			}
		}
		
		// Update Message Array
		$messageArray['country_code'] = (!empty($thread->post)) ? $thread->post->country_code : config('country.code');
		$messageArray['post_id'] = (!empty($thread->post)) ? $thread->post->id : null;
		$messageArray['name'] = $user->name;
		$messageArray['email'] = $user->email;
		$messageArray['phone'] = $user->phone;
		$messageArray['subject'] = t('New message about') . ': ' . $thread->post->title;
		if (!empty($message->filename)) {
			$messageArray['filename'] = $message->filename;
		}
		
		// Get the listing's auth field
		$authField = $user->auth_field ?? getAuthField();
		$messageArray['auth_field'] = $authField;
		$messageArray['to_auth_field'] = $authField;
		if (
			!empty($thread->post) && isset($thread->post->user_id)
			&& ($user->id == $thread->post->user_id)
			&& isset($thread->post->auth_field) && !empty($thread->post->auth_field)
		) {
			$messageArray['to_auth_field'] = $thread->post->auth_field;
		}
		
		// Add replier as a participant
		$participant = ThreadParticipant::firstOrCreate([
			'thread_id' => $thread->id,
			'user_id'   => $user->id,
		]);
		$participant->last_read = new Carbon();
		$participant->save();
		
		// Recipients
		if ($request->has('recipients')) {
			$thread->addParticipant($request->input('recipients'));
		} else {
			$thread->addParticipant($thread->post->user->id);
		}
		
		// Remove input file to prevent Laravel Queue serialization issue
		if (isset($messageArray['filename']) && !is_string($messageArray['filename'])) {
			unset($messageArray['filename']);
		}
		
		// Send Reply Notification (Email|SMS?)
		if (
			isset($messageArray['post_id'])
			&& array_key_exists('email', $messageArray)
			&& isset($messageArray['name'])
			&& isset($messageArray['body'])
		) {
			try {
				if (!isDemoDomain()) {
					if (isset($thread->users) && $thread->users->count() > 0) {
						foreach ($thread->users as $threadUser) {
							if (
								!empty($thread->post) && isset($thread->post->user_id)
								&& ($threadUser->id == $thread->post->user_id)
								&& isset($thread->post->auth_field) && !empty($thread->post->auth_field)
							) {
								// Update the listing's auth field
								$messageArray['to_auth_field'] = $thread->post->auth_field;
							}
							$messageArray['to_email'] = $threadUser->email ?? null;
							$messageArray['to_phone'] = $threadUser->phone ?? null;
							$messageArray['to_phone_hidden'] = $threadUser->phone_hidden ?? 0;
							Notification::send($threadUser, new ReplySent($messageArray));
						}
					}
				}
			} catch (\Throwable $e) {
				return $this->respondInternalError($e->getMessage());
			}
		}
		
		$data = [
			'success' => true,
			'message' => t('Your reply has been sent'),
			'result'  => (new ThreadResource($thread))->toArray($request),
		];
		
		return $this->respondUpdated($data);
	}
	
	/**
	 * Bulk updates
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @queryParam type string required The type of action to execute (markAsRead, markAsUnread, markAsImportant, markAsNotImportant or markAllAsRead).
	 *
	 * @urlParam ids string required The ID or comma-separated IDs list of thread(s).
	 *
	 * @param null $ids
	 * @return mixed
	 */
	public function bulkUpdate($ids = null)
	{
		$user = auth('sanctum')->user();
		
		// Get Selected Entries ID (IDs separated by comma accepted)
		$ids = explode(',', $ids);
		
		return $this->updateByType($ids, $user);
	}
	
	/**
	 * Delete thread(s)
	 *
	 * @authenticated
	 * @header Authorization Bearer {YOUR_AUTH_TOKEN}
	 *
	 * @urlParam ids string required The ID or comma-separated IDs list of thread(s).
	 *
	 * @param $ids
	 * @return \Illuminate\Http\JsonResponse
	 * @throws \Exception
	 */
	public function destroy($ids)
	{
		$user = auth('sanctum')->user();
		
		// Get Entries ID (IDs separated by comma accepted)
		$ids = explode(',', $ids);
		
		// Delete
		$res = false;
		foreach ($ids as $id) {
			// Get the Thread
			$thread = Thread::where((new Thread)->getTable() . '.id', $id)
				->forUser($user->id)
				->first();
			
			if (!empty($thread)) {
				if (empty($thread->deleted_by)) {
					// Delete the Entry for current user
					Thread::withoutTimestamps()
						->where('id', $thread->id)
						->update([
							'deleted_by' => $user->id,
						]);
					
					$res = true;
				} else {
					// If the 2nd user delete the Entry,
					// Delete the Entry (definitely)
					if ($thread->deleted_by != $user->id) {
						$res = $thread->forceDelete();
					}
				}
			}
		}
		if (!$res) {
			return $this->respondNoContent(t('no_deletion_is_done'));
		}
		
		// Confirmation
		$count = count($ids);
		if ($count > 1) {
			$msg = t('x entities has been deleted successfully', [
				'entities' => t('messages'),
				'count'    => $count,
			]);
		} else {
			$msg = t('1 entity has been deleted successfully', [
				'entity' => t('message'),
			]);
		}
		
		return $this->respondSuccess($msg);
	}
}
