import 'dart:io';
import 'dart:typed_data';

import 'package:audioplayers/audioplayers.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

/// Microphone capture for voice input.
///
/// Records Opus audio, which is what the server's speech-to-text expects.
class VoiceRecorder {
  final AudioRecorder _recorder = AudioRecorder();
  String? _path;

  Future<bool> hasPermission() => _recorder.hasPermission();

  Future<bool> start() async {
    if (!await _recorder.hasPermission()) return false;

    final directory = await getTemporaryDirectory();
    _path = '${directory.path}/voice_input.ogg';

    await _recorder.start(
      // Mono at Opus's native 48 kHz: the server reads the container to pick
      // the matching encoding, and a mismatched rate is rejected outright.
      const RecordConfig(
        encoder: AudioEncoder.opus,
        numChannels: 1,
        sampleRate: 48000,
      ),
      path: _path!,
    );

    return true;
  }

  /// Stops recording and returns the file path, or null when nothing usable
  /// was captured (for example the user released the button immediately).
  Future<String?> stop() async {
    final path = await _recorder.stop() ?? _path;

    if (path == null) return null;

    final file = File(path);

    if (!file.existsSync() || await file.length() < 1024) {
      return null;
    }

    return path;
  }

  Future<void> cancel() async {
    if (await _recorder.isRecording()) {
      await _recorder.stop();
    }
  }

  Future<void> dispose() => _recorder.dispose();
}

/// Plays the audio returned by the text-to-speech endpoint.
class SpeechPlayer {
  final AudioPlayer _player = AudioPlayer();
  bool _configured = false;

  Stream<void> get onComplete => _player.onPlayerComplete;

  /// Declares this as media playback so it uses the music stream and stays
  /// audible when the phone is on vibrate, rather than following the ringer.
  Future<void> _configure() async {
    if (_configured) return;

    await _player.setAudioContext(
      AudioContext(
        android: const AudioContextAndroid(
          contentType: AndroidContentType.speech,
          usageType: AndroidUsageType.media,
          audioFocus: AndroidAudioFocus.gain,
        ),
        iOS: AudioContextIOS(category: AVAudioSessionCategory.playback),
      ),
    );

    await _player.setReleaseMode(ReleaseMode.stop);
    _configured = true;
  }

  /// Writes the clip to disk before playing it. Playing from an in-memory
  /// BytesSource silently produces no sound on Android, whereas a real file
  /// plays reliably.
  Future<void> play(List<int> bytes) async {
    await _configure();
    await _player.stop();

    final directory = await getTemporaryDirectory();
    // A fresh name each time; reusing one path can leave the player on a
    // stale, already-finished source.
    final file = File(
      '${directory.path}/tts_${DateTime.now().millisecondsSinceEpoch}.mp3',
    );
    await file.writeAsBytes(Uint8List.fromList(bytes), flush: true);

    await _player.play(DeviceFileSource(file.path));
  }

  Future<void> stop() => _player.stop();

  Future<void> dispose() => _player.dispose();
}
