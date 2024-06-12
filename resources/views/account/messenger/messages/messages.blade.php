<?php
$thread ??= [];
$messages ??= [];
$totalMessages = (int)($totalMessages ?? 0);
?>
@if (!empty($messages) && $totalMessages > 0)
	@foreach($messages as $message)
		@include('account.messenger.messages.message', ['thread' => $thread, 'message' => $message])
	@endforeach
@endif