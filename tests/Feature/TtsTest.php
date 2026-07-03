<?php

it('requires text for speech synthesis', function () {
    $this->postJson('/tts', [])->assertStatus(422);
});

it('rejects an unsupported speech language', function () {
    $this->postJson('/tts', ['text' => 'hello', 'language' => 'fr'])->assertStatus(422);
});
