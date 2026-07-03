<?php

it('requires text for speech synthesis', function () {
    $this->postJson('/tts', [])->assertStatus(422);
});
