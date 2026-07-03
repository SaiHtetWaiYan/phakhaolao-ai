<?php

it('requires an audio file to transcribe', function () {
    $this->postJson('/transcribe', [])->assertStatus(422);
});
