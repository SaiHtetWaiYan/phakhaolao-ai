import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
      theme: _theme(Brightness.light, seed),
      darkTheme: _theme(Brightness.dark, seed),
      home: const ChatScreen(),
    );
  }

  ThemeData _theme(Brightness brightness, Color seed) {
    final scheme = ColorScheme.fromSeed(seedColor: seed, brightness: brightness);

    return ThemeData(
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
      ),
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

  /// 'auto', 'en' or 'lo'. A real value rather than null: a null popup-menu
  /// result is indistinguishable from dismissing the menu, so "Auto" could
  /// never be chosen.
  String _language = 'auto';

  bool _sending = false;
  bool _recording = false;
  bool _transcribing = false;
  int? _speakingIndex;

  @override
  void initState() {
    super.initState();
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

  String? get _apiLanguage => _language == 'auto' ? null : _language;

  void _notify(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
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
        responseLanguage: _apiLanguage,
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
    _player.stop();
    setState(() {
      _messages.clear();
      _conversationId = null;
      _speakingIndex = null;
    });
  }

  /// Opens a past conversation, replacing what is on screen.
  Future<void> _openConversation(String id) async {
    _player.stop();
    setState(() {
      _messages.clear();
      _sending = true;
      _speakingIndex = null;
    });

    try {
      final history = await _api.conversation(id);

      setState(() {
        _conversationId = id;
        _messages.addAll(
          history.map((m) => Message(text: m.text, fromUser: m.fromUser)),
        );
      });
      _scrollToBottom();
    } catch (e) {
      _notify(e is ApiException ? e.message : 'Could not open that chat.');
    } finally {
      if (mounted) setState(() => _sending = false);
    }
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

        final text = await _api.transcribe(path, language: _language);

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

  void _copy(String text) {
    Clipboard.setData(ClipboardData(text: text));
    _notify('Copied');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: _HistoryDrawer(
        api: _api,
        currentId: _conversationId,
        onOpen: _openConversation,
        onNewChat: _newChat,
      ),
      appBar: AppBar(
        title: const Text(
          'PhaKhaoLao AI',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
        ),
        actions: [
          PopupMenuButton<String>(
            tooltip: 'Reply language',
            icon: const Icon(Icons.translate),
            initialValue: _language,
            onSelected: (value) => setState(() => _language = value),
            itemBuilder: (context) => const [
              PopupMenuItem(value: 'auto', child: Text('Auto')),
              PopupMenuItem(value: 'en', child: Text('English')),
              PopupMenuItem(value: 'lo', child: Text('ລາວ')),
            ],
          ),
          IconButton(
            tooltip: 'New chat',
            icon: const Icon(Icons.edit_square),
            onPressed: _messages.isEmpty ? null : _newChat,
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: _messages.isEmpty && !_sending
                ? const _EmptyState()
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                    itemCount: _messages.length + (_sending ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index >= _messages.length) {
                        return const _TypingIndicator();
                      }

                      final message = _messages[index];

                      return message.fromUser
                          ? _UserMessage(text: message.text)
                          : _AssistantMessage(
                              message: message,
                              speaking: _speakingIndex == index,
                              onSpeak: () => _speak(index),
                              onCopy: () => _copy(message.text),
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

/// Slide-out list of past chats, loaded from the server each time it opens.
class _HistoryDrawer extends StatelessWidget {
  const _HistoryDrawer({
    required this.api,
    required this.currentId,
    required this.onOpen,
    required this.onNewChat,
  });

  final ApiClient api;
  final String? currentId;
  final void Function(String id) onOpen;
  final VoidCallback onNewChat;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 12, 8),
              child: Row(
                children: [
                  Image.asset('assets/logo.png', width: 28, height: 28),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text('PhaKhaoLao AI', style: theme.textTheme.titleMedium),
                  ),
                ],
              ),
            ),
            ListTile(
              leading: const Icon(Icons.edit_square),
              title: const Text('New chat'),
              onTap: () {
                Navigator.pop(context);
                onNewChat();
              },
            ),
            const Divider(height: 1),
            Expanded(
              child: FutureBuilder<List<Conversation>>(
                future: api.conversations(),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }

                  if (snapshot.hasError) {
                    return const _DrawerNotice('Could not load your chats.');
                  }

                  final conversations = snapshot.data ?? [];

                  if (conversations.isEmpty) {
                    return const _DrawerNotice('No chats yet.');
                  }

                  return ListView.builder(
                    padding: EdgeInsets.zero,
                    itemCount: conversations.length,
                    itemBuilder: (context, index) {
                      final conversation = conversations[index];

                      return ListTile(
                        dense: true,
                        selected: conversation.id == currentId,
                        leading: const Icon(Icons.chat_bubble_outline, size: 20),
                        title: Text(
                          conversation.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        onTap: () {
                          Navigator.pop(context);
                          onOpen(conversation.id);
                        },
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DrawerNotice extends StatelessWidget {
  const _DrawerNotice(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          text,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall,
        ),
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
            Image.asset('assets/logo.png', width: 88, height: 88),
            const SizedBox(height: 20),
            Text(
              'Ask about Lao biodiversity',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
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

/// User turns sit in a bubble on the right, as in the ChatGPT app.
class _UserMessage extends StatelessWidget {
  const _UserMessage({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.8,
        ),
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: theme.colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(text, style: theme.textTheme.bodyLarge),
      ),
    );
  }
}

/// Assistant turns run full width with no bubble, with actions underneath.
class _AssistantMessage extends StatelessWidget {
  const _AssistantMessage({
    required this.message,
    required this.speaking,
    required this.onSpeak,
    required this.onCopy,
  });

  final Message message;
  final bool speaking;
  final VoidCallback onSpeak;
  final VoidCallback onCopy;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colour = message.failed ? theme.colorScheme.error : null;

    return Padding(
      padding: const EdgeInsets.only(top: 8, bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 2, right: 10),
                child: Image.asset('assets/logo.png', width: 24, height: 24),
              ),
              Expanded(
                child: MarkdownBody(
                  data: message.text,
                  selectable: true,
                  styleSheet: MarkdownStyleSheet.fromTheme(theme).copyWith(
                    p: theme.textTheme.bodyLarge?.copyWith(color: colour),
                    listBullet: theme.textTheme.bodyLarge?.copyWith(color: colour),
                  ),
                  onTapLink: (text, href, title) async {
                    if (href == null) return;
                    final uri = Uri.tryParse(href);
                    if (uri != null && await canLaunchUrl(uri)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  },
                ),
              ),
            ],
          ),
          if (!message.failed)
            Padding(
              padding: const EdgeInsets.only(left: 34, top: 2),
              child: Row(
                children: [
                  _ActionButton(
                    icon: speaking ? Icons.stop_circle_outlined : Icons.volume_up_outlined,
                    tooltip: speaking ? 'Stop' : 'Listen',
                    active: speaking,
                    onPressed: onSpeak,
                  ),
                  _ActionButton(
                    icon: Icons.copy_rounded,
                    tooltip: 'Copy',
                    onPressed: onCopy,
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
    this.active = false,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback onPressed;
  final bool active;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return IconButton(
      tooltip: tooltip,
      iconSize: 18,
      visualDensity: VisualDensity.compact,
      constraints: const BoxConstraints(minWidth: 40, minHeight: 36),
      color: active
          ? theme.colorScheme.primary
          : theme.colorScheme.onSurfaceVariant,
      icon: Icon(icon),
      onPressed: onPressed,
    );
  }
}

class _TypingIndicator extends StatelessWidget {
  const _TypingIndicator();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          Image.asset('assets/logo.png', width: 24, height: 24),
          const SizedBox(width: 12),
          const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ],
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
    final busy = sending || recording || transcribing;

    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 6, 12, 10),
        child: Container(
          decoration: BoxDecoration(
            color: theme.colorScheme.surfaceContainerHighest,
            borderRadius: BorderRadius.circular(26),
          ),
          padding: const EdgeInsets.fromLTRB(18, 4, 6, 4),
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
                  style: theme.textTheme.bodyLarge,
                  decoration: InputDecoration(
                    hintText: recording
                        ? 'Listening…'
                        : transcribing
                            ? 'Transcribing…'
                            : 'Ask anything',
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              IconButton(
                tooltip: recording ? 'Stop recording' : 'Voice input',
                onPressed: sending || transcribing ? null : onMic,
                color: recording ? theme.colorScheme.error : null,
                icon: transcribing
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(recording ? Icons.stop_circle : Icons.mic_none),
              ),
              IconButton.filled(
                onPressed: busy ? null : onSend,
                icon: const Icon(Icons.arrow_upward, size: 20),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
