<?php

use App\Ai\Agents\ChatAssistant;

it('loads the chat page', function () {
    $this->get(route('chat'))
        ->assertSuccessful()
        ->assertSee('How can I help you today?');
});

it('requires a message to send', function () {
    $this->postJson(route('chat.send'), ['message' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

it('rejects messages exceeding max length', function () {
    $this->postJson(route('chat.send'), ['message' => str_repeat('a', 5001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

it('streams a response from the assistant', function () {
    ChatAssistant::fake(['Hello! How can I help you?']);

    $response = $this->postJson(route('chat.send'), ['message' => 'Hi there']);

    $response->assertSuccessful();

    ChatAssistant::assertPrompted('Hi there');
});

it('stores user messages in the session', function () {
    ChatAssistant::fake(['Response']);

    $this->postJson(route('chat.send'), ['message' => 'Test message']);

    $this->assertEquals(
        [['role' => 'user', 'content' => 'Test message']],
        session('chat_messages')
    );
});

it('saves assistant responses to the session', function () {
    $this->session(['chat_messages' => [
        ['role' => 'user', 'content' => 'Hello'],
    ]]);

    $this->postJson(route('chat.save-response'), ['content' => 'Hi there!'])
        ->assertSuccessful()
        ->assertJson(['status' => 'ok']);

    $this->assertEquals([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there!'],
    ], session('chat_messages'));
});

it('requires content when saving a response', function () {
    $this->postJson(route('chat.save-response'), ['content' => ''])
        ->assertUnprocessable();
});

it('clears chat history', function () {
    $this->session(['chat_messages' => [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi!'],
    ]]);

    $this->postJson(route('chat.clear'))
        ->assertSuccessful()
        ->assertJson(['status' => 'ok']);

    $this->assertNull(session('chat_messages'));
});

it('sends conversation history to the agent', function () {
    ChatAssistant::fake(['Follow-up response']);

    $this->session(['chat_messages' => [
        ['role' => 'user', 'content' => 'First message'],
        ['role' => 'assistant', 'content' => 'First response'],
    ]]);

    $this->postJson(route('chat.send'), ['message' => 'Second message']);

    ChatAssistant::assertPrompted('Second message');

    $this->assertEquals([
        ['role' => 'user', 'content' => 'First message'],
        ['role' => 'assistant', 'content' => 'First response'],
        ['role' => 'user', 'content' => 'Second message'],
    ], session('chat_messages'));
});

it('routes broad list queries through the agent', function () {
    ChatAssistant::fake(['Here are some plants...']);

    $this->postJson(route('chat.send'), ['message' => 'List some plants'])
        ->assertSuccessful();

    ChatAssistant::assertPrompted('List some plants');
});
