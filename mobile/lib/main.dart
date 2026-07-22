import 'package:flutter/material.dart';
import 'package:flutter_markdown_plus/flutter_markdown_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_client.dart';

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

  String? _conversationId;
  String? _language; // null = auto, 'en', 'lo'
  bool _sending = false;

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
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

                      return _MessageBubble(message: _messages[index]);
                    },
                  ),
          ),
          _Composer(
            controller: _controller,
            sending: _sending,
            onSend: _send,
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
  const _MessageBubble({required this.message});

  final Message message;

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
            : MarkdownBody(
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
    required this.onSend,
  });

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
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
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => onSend(),
                decoration: InputDecoration(
                  hintText: 'Ask a question…',
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
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: sending ? null : onSend,
              icon: const Icon(Icons.arrow_upward),
            ),
          ],
        ),
      ),
    );
  }
}
