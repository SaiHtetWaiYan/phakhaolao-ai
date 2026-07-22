import 'package:flutter/material.dart';
import 'package:flutter_markdown_plus/flutter_markdown_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_client.dart';
import 'speech.dart';

/// Point this at your server. Use http://10.0.2.2:8000 for an emulator talking
/// to a server running on the host machine.
const String kBaseUrl = 'https://phakhaolao-ai.saihtet.dev';

void main() => runApp(const PhaKhaoLaoApp());

class PhaKhaoLaoApp extends StatelessWidget {
  const PhaKhaoLaoApp({super.key});

  @override
  Widget build(BuildContext context) {
    const seed = Color(0xFF2E7D32);

    return MaterialApp(
      title: 'PhaKhaoLao AI',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: seed, brightness: Brightness.light),
      darkTheme: ThemeData(colorSchemeSeed: seed, brightness: Brightness.dark),
      home: const ChatScreen(),
    );
  }
}

class Message {
  Message({required this.text, required this.fromUser, this.failed = false});

  final String text;
  final bool fromUser;
  final bool failed;
}

class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _api = ApiClient(baseUrl: kBaseUrl);
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final _messages = <Message>[];
  final _recorder = VoiceRecorder();
  final _player = SpeechPlayer();

  String? _conversationId;
  String? _language; // null = auto, 'en', 'lo'
  bool _sending = false;
  bool _recording = false;
  bool _transcribing = false;
  int? _speakingIndex;

  @override
  void initState() {
    super.initState();
    // Clear the speaker highlight once playback finishes on its own.
    _player.onComplete.listen((_) {
      if (mounted) setState(() => _speakingIndex = null);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    _recorder.dispose();
    _player.dispose();
    super.dispose();
  }

  void _notify(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _toggleRecording() async {
    if (_transcribing || _sending) return;

    if (_recording) {
      setState(() {
        _recording = false;
        _transcribing = true;
      });

      try {
        final path = await _recorder.stop();

        if (path == null) {
          _notify('Recording was too short.');
          return;
        }

        final text = await _api.transcribe(path, language: _language ?? 'auto');

        if (text.isEmpty) {
          _notify('Could not hear anything. Please try again.');
          return;
        }

        _controller.text = text;
        _controller.selection = TextSelection.fromPosition(
          TextPosition(offset: text.length),
        );
      } catch (e) {
        _notify(e is ApiException ? e.message : 'Transcription failed.');
      } finally {
        if (mounted) setState(() => _transcribing = false);
      }

      return;
    }

    if (!await _recorder.start()) {
      _notify('Microphone permission is required for voice input.');
      return;
    }

    setState(() => _recording = true);
  }

  Future<void> _speak(int index) async {
    // Tapping the speaker again stops playback.
    if (_speakingIndex == index) {
      await _player.stop();
      setState(() => _speakingIndex = null);
      return;
    }

    setState(() => _speakingIndex = index);

    try {
      await _player.play(await _api.speech(_messages[index].text));
    } catch (e) {
      if (mounted) setState(() => _speakingIndex = null);
      _notify(e is ApiException ? e.message : 'Could not play audio.');
    }
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() {
      _messages.add(Message(text: text, fromUser: true));
      _sending = true;
      _controller.clear();
    });
    _scrollToBottom();

    try {
      final reply = await _api.send(
        text,
        conversationId: _conversationId,
        responseLanguage: _language,
      );

      setState(() {
        _conversationId = reply.conversationId;
        _messages.add(Message(text: reply.reply, fromUser: false));
      });
    } catch (e) {
      setState(() {
        _messages.add(Message(
          text: e is ApiException
              ? e.message
              : 'Could not reach the server. Check your connection.',
          fromUser: false,
          failed: true,
        ));
      });
    } finally {
      setState(() => _sending = false);
      _scrollToBottom();
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  void _newChat() {
    setState(() {
      _messages.clear();
      _conversationId = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('PhaKhaoLao AI'),
        actions: [
          PopupMenuButton<String?>(
            tooltip: 'Reply language',
            icon: const Icon(Icons.translate),
            initialValue: _language,
            onSelected: (value) => setState(() => _language = value),
            itemBuilder: (context) => const [
              PopupMenuItem(value: null, child: Text('Auto')),
              PopupMenuItem(value: 'en', child: Text('English')),
              PopupMenuItem(value: 'lo', child: Text('ລາວ')),
            ],
          ),
          IconButton(
            tooltip: 'New chat',
            icon: const Icon(Icons.add_comment_outlined),
            onPressed: _messages.isEmpty ? null : _newChat,
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: _messages.isEmpty
                ? const _EmptyState()
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(12),
                    itemCount: _messages.length + (_sending ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index >= _messages.length) {
                        return const _TypingIndicator();
                      }

                      return _MessageBubble(
                        message: _messages[index],
                        speaking: _speakingIndex == index,
                        onSpeak: () => _speak(index),
                      );
                    },
                  ),
          ),
          _Composer(
            controller: _controller,
            sending: _sending,
            recording: _recording,
            transcribing: _transcribing,
            onSend: _send,
            onMic: _toggleRecording,
          ),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.eco_outlined, size: 64, color: theme.colorScheme.primary),
            const SizedBox(height: 16),
            Text('Ask about Lao biodiversity',
                style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              'Species, champions, library resources and stories from the '
              'PhaKhaoLao catalogue.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({
    required this.message,
    required this.speaking,
    required this.onSpeak,
  });

  final Message message;
  final bool speaking;
  final VoidCallback onSpeak;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final fromUser = message.fromUser;

    final background = message.failed
        ? theme.colorScheme.errorContainer
        : fromUser
            ? theme.colorScheme.primaryContainer
            : theme.colorScheme.surfaceContainerHighest;

    final foreground = message.failed
        ? theme.colorScheme.onErrorContainer
        : fromUser
            ? theme.colorScheme.onPrimaryContainer
            : theme.colorScheme.onSurface;

    return Align(
      alignment: fromUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.85,
        ),
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(16),
        ),
        child: fromUser
            ? Text(message.text, style: TextStyle(color: foreground))
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  MarkdownBody(
                    data: message.text,
                    selectable: true,
                    styleSheet: MarkdownStyleSheet.fromTheme(theme).copyWith(
                      p: theme.textTheme.bodyMedium?.copyWith(color: foreground),
                    ),
                    onTapLink: (text, href, title) async {
                      if (href == null) return;
                      final uri = Uri.tryParse(href);
                      if (uri != null && await canLaunchUrl(uri)) {
                        await launchUrl(uri, mode: LaunchMode.externalApplication);
                      }
                    },
                  ),
                  if (!message.failed)
                    SizedBox(
                      height: 32,
                      child: IconButton(
                        tooltip: speaking ? 'Stop' : 'Listen',
                        padding: EdgeInsets.zero,
                        visualDensity: VisualDensity.compact,
                        iconSize: 20,
                        color: speaking ? theme.colorScheme.primary : foreground.withValues(alpha: 0.7),
                        icon: Icon(speaking ? Icons.stop_circle_outlined : Icons.volume_up_outlined),
                        onPressed: onSpeak,
                      ),
                    ),
                ],
              ),
      ),
    );
  }
}

class _TypingIndicator extends StatelessWidget {
  const _TypingIndicator();

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(16),
        ),
        child: const SizedBox(
          width: 20,
          height: 20,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.sending,
    required this.recording,
    required this.transcribing,
    required this.onSend,
    required this.onMic,
  });

  final TextEditingController controller;
  final bool sending;
  final bool recording;
  final bool transcribing;
  final VoidCallback onSend;
  final VoidCallback onMic;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                minLines: 1,
                maxLines: 5,
                enabled: !recording && !transcribing,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => onSend(),
                decoration: InputDecoration(
                  hintText: recording
                      ? 'Listening… tap the mic to stop'
                      : transcribing
                          ? 'Transcribing…'
                          : 'Ask a question…',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 4),
            IconButton(
              tooltip: recording ? 'Stop recording' : 'Voice input',
              onPressed: sending || transcribing ? null : onMic,
              color: recording ? theme.colorScheme.error : null,
              icon: transcribing
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Icon(recording ? Icons.stop_circle : Icons.mic_none),
            ),
            IconButton.filled(
              onPressed: sending || recording || transcribing ? null : onSend,
              icon: const Icon(Icons.arrow_upward),
            ),
          ],
        ),
      ),
    );
  }
}
