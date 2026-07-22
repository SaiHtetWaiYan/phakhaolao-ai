import 'package:flutter/material.dart';
import 'dart:io';

import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_markdown_plus/flutter_markdown_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_client.dart';
import 'speech.dart';
import 'strings.dart';

/// Point this at your server. Use http://10.0.2.2:8000 for an emulator talking
/// to a server running on the host machine.
const String kBaseUrl = 'https://phakhaolao-ai.saihtet.dev';

void main() => runApp(const PhaKhaoLaoApp());

class PhaKhaoLaoApp extends StatefulWidget {
  const PhaKhaoLaoApp({super.key});

  @override
  State<PhaKhaoLaoApp> createState() => _PhaKhaoLaoAppState();
}

class _PhaKhaoLaoAppState extends State<PhaKhaoLaoApp> {
  static const _themeKey = 'theme_mode';

  ThemeMode _themeMode = ThemeMode.system;

  @override
  void initState() {
    super.initState();
    _restoreTheme();
  }

  Future<void> _restoreTheme() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString(_themeKey);

    if (stored == null || !mounted) return;

    setState(() {
      _themeMode = ThemeMode.values.firstWhere(
        (mode) => mode.name == stored,
        orElse: () => ThemeMode.system,
      );
    });
  }

  Future<void> _setTheme(ThemeMode mode) async {
    setState(() => _themeMode = mode);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_themeKey, mode.name);
  }

  @override
  Widget build(BuildContext context) {
    const seed = Color(0xFF2E7D32);

    return MaterialApp(
      title: 'PhaKhaoLao AI',
      debugShowCheckedModeBanner: false,
      theme: _theme(Brightness.light, seed),
      darkTheme: _theme(Brightness.dark, seed),
      themeMode: _themeMode,
      home: ChatScreen(themeMode: _themeMode, onThemeChanged: _setTheme),
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
  Message({
    required this.text,
    required this.fromUser,
    this.failed = false,
    this.localImagePath,
    this.imageUrl,
  });

  final String text;
  final bool fromUser;
  final bool failed;

  /// Set while the photo is still only on the device.
  final String? localImagePath;

  /// Set once the server has stored it.
  final String? imageUrl;
}

class ChatScreen extends StatefulWidget {
  const ChatScreen({
    super.key,
    required this.themeMode,
    required this.onThemeChanged,
  });

  final ThemeMode themeMode;
  final ValueChanged<ThemeMode> onThemeChanged;

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

  final _picker = ImagePicker();
  String? _pendingImagePath;

  bool _sending = false;
  bool _recording = false;
  bool _transcribing = false;
  int? _speakingIndex;

  static const _languageKey = 'reply_language';

  @override
  void initState() {
    super.initState();
    _player.onComplete.listen((_) {
      if (mounted) setState(() => _speakingIndex = null);
    });
    _restoreLanguage();
  }

  Future<void> _restoreLanguage() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString(_languageKey);

    if (stored == null || !mounted) return;

    setState(() => _language = stored);
  }

  Future<void> _setLanguage(String language) async {
    setState(() => _language = language);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_languageKey, language);
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

  Strings get _t => Strings.of(_language);

  void _notify(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    final imagePath = _pendingImagePath;

    // A photo alone is a valid message: it asks what species this is.
    if ((text.isEmpty && imagePath == null) || _sending) return;

    setState(() {
      _messages.add(Message(
        text: text,
        fromUser: true,
        localImagePath: imagePath,
      ));
      _sending = true;
      _pendingImagePath = null;
      _controller.clear();
    });
    _scrollToBottom();

    try {
      final reply = await _api.send(
        text,
        conversationId: _conversationId,
        responseLanguage: _apiLanguage,
        imagePath: imagePath,
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
              : _t('network_error'),
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
        _messages.addAll(history.map((m) => Message(
              text: m.text,
              fromUser: m.fromUser,
              imageUrl: m.imageUrl,
            )));
      });
      _scrollToBottom();
    } catch (e) {
      _notify(e is ApiException ? e.message : _t('open_failed'));
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
          _notify(_t('too_short'));
          return;
        }

        final text = await _api.transcribe(path, language: _language);

        if (text.isEmpty) {
          _notify(_t('not_heard'));
          return;
        }

        _controller.text = text;
        _controller.selection = TextSelection.fromPosition(
          TextPosition(offset: text.length),
        );
      } catch (e) {
        _notify(e is ApiException ? e.message : _t('transcribe_failed'));
      } finally {
        if (mounted) setState(() => _transcribing = false);
      }

      return;
    }

    if (!await _recorder.start()) {
      _notify(_t('mic_permission'));
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
      _notify(e is ApiException ? e.message : _t('audio_failed'));
    }
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final file = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        imageQuality: 85,
      );

      if (file == null) return;

      setState(() => _pendingImagePath = file.path);
    } catch (e) {
      _notify(_t('image_failed'));
    }
  }

  void _attach() {
    showModalBottomSheet<void>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: Text(_t('take_photo')),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: Text(_t('choose_photo')),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openLink(String? href) async {
    final uri = href == null ? null : Uri.tryParse(href);

    if (uri == null) return;

    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);

      if (!opened) _notify(_t('link_failed'));
    } catch (_) {
      _notify(_t('link_failed'));
    }
  }

  void _copy(String text) {
    Clipboard.setData(ClipboardData(text: text));
    _notify(_t('copied'));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      drawer: _HistoryDrawer(
        api: _api,
        t: _t,
        themeMode: widget.themeMode,
        onThemeChanged: widget.onThemeChanged,
        currentId: _conversationId,
        onOpen: _openConversation,
        onNewChat: _newChat,
        onDeleted: (id) {
          if (id == _conversationId) _newChat();
        },
      ),
      appBar: AppBar(
        title: const Text(
          'PhaKhaoLao AI',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
        ),
        actions: [
          PopupMenuButton<String>(
            tooltip: _t('reply_language'),
            icon: const Icon(Icons.translate),
            initialValue: _language,
            onSelected: _setLanguage,
            itemBuilder: (context) => [
              PopupMenuItem(value: 'auto', child: Text(_t('language_auto'))),
              const PopupMenuItem(value: 'en', child: Text('English')),
              const PopupMenuItem(value: 'lo', child: Text('ລາວ')),
            ],
          ),
          IconButton(
            tooltip: _t('new_chat'),
            icon: const Icon(Icons.edit_square),
            onPressed: _messages.isEmpty ? null : _newChat,
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: _messages.isEmpty && !_sending
                ? _EmptyState(t: _t)
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
                          ? _UserMessage(message: message, baseUrl: kBaseUrl)
                          : _AssistantMessage(
                              message: message,
                              t: _t,
                              speaking: _speakingIndex == index,
                              onSpeak: () => _speak(index),
                              onCopy: () => _copy(message.text),
                              onOpenLink: _openLink,
                            );
                    },
                  ),
          ),
          _Composer(
            t: _t,
            baseUrl: kBaseUrl,
            pendingImagePath: _pendingImagePath,
            onAttach: _attach,
            onRemoveImage: () => setState(() => _pendingImagePath = null),
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
class _HistoryDrawer extends StatefulWidget {
  const _HistoryDrawer({
    required this.api,
    required this.t,
    required this.themeMode,
    required this.onThemeChanged,
    required this.currentId,
    required this.onOpen,
    required this.onNewChat,
    required this.onDeleted,
  });

  final ApiClient api;
  final Strings t;
  final ThemeMode themeMode;
  final ValueChanged<ThemeMode> onThemeChanged;
  final String? currentId;
  final void Function(String id) onOpen;
  final VoidCallback onNewChat;
  final void Function(String id) onDeleted;

  @override
  State<_HistoryDrawer> createState() => _HistoryDrawerState();
}

class _HistoryDrawerState extends State<_HistoryDrawer> {
  late Future<List<Conversation>> _future = widget.api.conversations();

  void _reload() => setState(() => _future = widget.api.conversations());

  /// Confirms first: deleting a conversation cannot be undone.
  Future<void> _confirmDelete(Conversation conversation) async {
    final t = widget.t;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('delete_title')),
        content: Text(t('delete_body')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('delete')),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    try {
      await widget.api.deleteConversation(conversation.id);
      widget.onDeleted(conversation.id);
      _reload();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e is ApiException ? e.message : t('delete_failed'))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final t = widget.t;

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
              title: Text(t('new_chat')),
              onTap: () {
                Navigator.pop(context);
                widget.onNewChat();
              },
            ),
            const Divider(height: 1),
            Expanded(
              child: FutureBuilder<List<Conversation>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const Center(child: CircularProgressIndicator());
                  }

                  if (snapshot.hasError) {
                    return _DrawerNotice(t('chats_failed'));
                  }

                  final conversations = snapshot.data ?? [];

                  if (conversations.isEmpty) {
                    return _DrawerNotice(t('no_chats'));
                  }

                  return ListView.builder(
                    padding: EdgeInsets.zero,
                    itemCount: conversations.length,
                    itemBuilder: (context, index) {
                      final conversation = conversations[index];

                      return ListTile(
                        dense: true,
                        selected: conversation.id == widget.currentId,
                        leading: const Icon(Icons.chat_bubble_outline, size: 20),
                        title: Text(
                          conversation.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: IconButton(
                          tooltip: t('delete'),
                          iconSize: 18,
                          visualDensity: VisualDensity.compact,
                          icon: const Icon(Icons.delete_outline),
                          onPressed: () => _confirmDelete(conversation),
                        ),
                        onTap: () {
                          Navigator.pop(context);
                          widget.onOpen(conversation.id);
                        },
                      );
                    },
                  );
                },
              ),
            ),
            const Divider(height: 1),
            _AppearanceTile(
              t: t,
              themeMode: widget.themeMode,
              onChanged: widget.onThemeChanged,
            ),
          ],
        ),
      ),
    );
  }
}

/// Light / dark / follow-system, pinned to the bottom of the drawer.
class _AppearanceTile extends StatelessWidget {
  const _AppearanceTile({
    required this.t,
    required this.themeMode,
    required this.onChanged,
  });

  final Strings t;
  final ThemeMode themeMode;
  final ValueChanged<ThemeMode> onChanged;

  static const _icons = {
    ThemeMode.system: Icons.brightness_auto_outlined,
    ThemeMode.light: Icons.light_mode_outlined,
    ThemeMode.dark: Icons.dark_mode_outlined,
  };

  String _label(ThemeMode mode) => switch (mode) {
        ThemeMode.system => t('theme_system'),
        ThemeMode.light => t('theme_light'),
        ThemeMode.dark => t('theme_dark'),
      };

  @override
  Widget build(BuildContext context) {
    return PopupMenuButton<ThemeMode>(
      tooltip: t('appearance'),
      initialValue: themeMode,
      onSelected: onChanged,
      position: PopupMenuPosition.over,
      itemBuilder: (context) => ThemeMode.values
          .map((mode) => PopupMenuItem(
                value: mode,
                child: Row(
                  children: [
                    Icon(_icons[mode], size: 20),
                    const SizedBox(width: 12),
                    Text(_label(mode)),
                  ],
                ),
              ))
          .toList(),
      child: ListTile(
        leading: Icon(_icons[themeMode]),
        title: Text(t('appearance')),
        subtitle: Text(_label(themeMode)),
        trailing: const Icon(Icons.arrow_drop_down),
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
  const _EmptyState({required this.t});

  final Strings t;

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
              t('welcome_title'),
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              t('welcome_subtitle'),
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
  const _UserMessage({required this.message, required this.baseUrl});

  final Message message;
  final String baseUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final hasText = message.text.isNotEmpty;

    // Local file while the photo is uploading, server copy once stored.
    final image = message.localImagePath != null
        ? Image.file(File(message.localImagePath!), fit: BoxFit.cover)
        : message.imageUrl != null
            ? Image.network('$baseUrl${message.imageUrl}', fit: BoxFit.cover)
            : null;

    return Align(
      alignment: Alignment.centerRight,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.8,
        ),
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: EdgeInsets.symmetric(
          horizontal: image == null ? 16 : 6,
          vertical: image == null ? 10 : 6,
        ),
        decoration: BoxDecoration(
          color: theme.colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            if (image != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxHeight: 220),
                  child: image,
                ),
              ),
            if (hasText)
              Padding(
                padding: EdgeInsets.fromLTRB(
                  image == null ? 0 : 10,
                  image == null ? 0 : 8,
                  image == null ? 0 : 10,
                  image == null ? 0 : 2,
                ),
                child: Text(message.text, style: theme.textTheme.bodyLarge),
              ),
          ],
        ),
      ),
    );
  }
}

/// Assistant turns run full width with no bubble, with actions underneath.
class _AssistantMessage extends StatelessWidget {
  const _AssistantMessage({
    required this.message,
    required this.t,
    required this.speaking,
    required this.onSpeak,
    required this.onCopy,
    required this.onOpenLink,
  });

  final Message message;
  final Strings t;
  final ValueChanged<String?> onOpenLink;
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
                    a: TextStyle(
                      color: theme.colorScheme.primary,
                      decoration: TextDecoration.underline,
                    ),
                  ),
                  imageBuilder: (uri, title, alt) => _RemoteImage(url: uri.toString()),
                  onTapLink: (text, href, title) => onOpenLink(href),
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
                    tooltip: speaking ? t('stop') : t('listen'),
                    active: speaking,
                    onPressed: onSpeak,
                  ),
                  _ActionButton(
                    icon: Icons.copy_rounded,
                    tooltip: t('copy'),
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
    required this.t,
    required this.baseUrl,
    required this.pendingImagePath,
    required this.onAttach,
    required this.onRemoveImage,
    required this.controller,
    required this.sending,
    required this.recording,
    required this.transcribing,
    required this.onSend,
    required this.onMic,
  });

  final Strings t;
  final String baseUrl;
  final String? pendingImagePath;
  final VoidCallback onAttach;
  final VoidCallback onRemoveImage;
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
          padding: const EdgeInsets.fromLTRB(6, 4, 6, 4),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (pendingImagePath != null)
                Align(
                  alignment: Alignment.centerLeft,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(10, 8, 10, 4),
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            File(pendingImagePath!),
                            width: 72,
                            height: 72,
                            fit: BoxFit.cover,
                          ),
                        ),
                        Positioned(
                          top: -10,
                          right: -10,
                          child: IconButton(
                            tooltip: t('remove'),
                            iconSize: 18,
                            visualDensity: VisualDensity.compact,
                            icon: const Icon(Icons.cancel),
                            onPressed: onRemoveImage,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              IconButton(
                tooltip: t('attach'),
                onPressed: busy ? null : onAttach,
                icon: const Icon(Icons.add_photo_alternate_outlined),
              ),
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
                        ? t('listening')
                        : transcribing
                            ? t('transcribing')
                            : t('placeholder'),
                    border: InputBorder.none,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
              IconButton(
                tooltip: recording ? t('stop') : t('listen'),
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
            ],
          ),
        ),
      ),
    );
  }
}


/// An image referenced by a reply, sized to the bubble and degrading to a
/// placeholder rather than a red error box when it cannot be fetched.
class _RemoteImage extends StatelessWidget {
  const _RemoteImage({required this.url});

  final String url;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.network(
          url,
          fit: BoxFit.cover,
          loadingBuilder: (context, child, progress) => progress == null
              ? child
              : const SizedBox(
                  height: 140,
                  child: Center(
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                ),
          errorBuilder: (context, error, stack) => Container(
            height: 100,
            alignment: Alignment.center,
            color: theme.colorScheme.surfaceContainerHighest,
            child: Icon(
              Icons.broken_image_outlined,
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ),
      ),
    );
  }
}
