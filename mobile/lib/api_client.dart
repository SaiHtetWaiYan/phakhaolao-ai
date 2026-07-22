import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Talks to the PhaKhaoLao AI v1 API.
///
/// There is no login: the app generates a device token once per install and
/// sends it on every request, which is what the server uses to own and scope
/// conversations.
class ApiClient {
  ApiClient({required this.baseUrl});

  final String baseUrl;
  String? _deviceToken;

  static const _tokenKey = 'device_token';

  Future<String> _token() async {
    if (_deviceToken != null) return _deviceToken!;

    final prefs = await SharedPreferences.getInstance();
    var token = prefs.getString(_tokenKey);

    if (token == null) {
      token = _generateToken();
      await prefs.setString(_tokenKey, token);
    }

    return _deviceToken = token;
  }

  /// A UUID-v4-shaped token, generated once and then reused forever.
  String _generateToken() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;

    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();

    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  Future<Map<String, String>> _headers() async => {
        'X-Device-Token': await _token(),
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

  /// Sends a message, optionally with a photo to identify, and returns the
  /// assistant's full reply.
  Future<ChatReply> send(
    String message, {
    String? conversationId,
    String? responseLanguage,
    String? imagePath,
  }) async {
    final http.Response response;

    if (imagePath == null) {
      response = await http
          .post(
            Uri.parse('$baseUrl/api/v1/chat'),
            headers: await _headers(),
            body: jsonEncode({
              'message': message,
              'conversation_id': ?conversationId,
              'response_language': ?responseLanguage,
            }),
          )
          .timeout(const Duration(seconds: 120));
    } else {
      final request = http.MultipartRequest('POST', Uri.parse('$baseUrl/api/v1/chat'))
        ..headers.addAll({
          'X-Device-Token': await _token(),
          'Accept': 'application/json',
        })
        ..fields['message'] = message
        // Without an explicit type this uploads as application/octet-stream,
        // which the vision model refuses.
        ..files.add(await http.MultipartFile.fromPath(
          'image',
          imagePath,
          contentType: MediaType('image', _imageSubtype(imagePath)),
        ));

      if (conversationId != null) request.fields['conversation_id'] = conversationId;
      if (responseLanguage != null) {
        request.fields['response_language'] = responseLanguage;
      }

      response = await http.Response.fromStream(
        await request.send().timeout(const Duration(seconds: 150)),
      );
    }

    final body = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;

    if (response.statusCode == 429) {
      throw ApiException(body['message'] as String? ?? 'Daily limit reached.');
    }

    if (response.statusCode != 200) {
      throw ApiException(body['message'] as String? ?? 'Something went wrong.');
    }

    return ChatReply(
      conversationId: body['conversation_id'] as String,
      reply: body['reply'] as String,
      imageUrl: body['image_url'] as String?,
    );
  }

  /// Lists this device's conversations, most recently updated first.
  Future<List<Conversation>> conversations() async {
    final response = await http
        .get(Uri.parse('$baseUrl/api/v1/conversations'), headers: await _headers())
        .timeout(const Duration(seconds: 30));

    if (response.statusCode != 200) {
      throw const ApiException('Could not load your chats.');
    }

    final body = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;

    return (body['data'] as List<dynamic>)
        .map((item) => Conversation.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  /// Loads one conversation with its full message history.
  Future<List<ChatMessage>> conversation(String id) async {
    final response = await http
        .get(Uri.parse('$baseUrl/api/v1/conversations/$id'), headers: await _headers())
        .timeout(const Duration(seconds: 30));

    if (response.statusCode != 200) {
      throw const ApiException('Could not open that chat.');
    }

    final body = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;

    return (body['messages'] as List<dynamic>)
        .map((item) => ChatMessage.fromJson(item as Map<String, dynamic>))
        .toList();
  }

  Future<void> deleteConversation(String id) async {
    final response = await http
        .delete(Uri.parse('$baseUrl/api/v1/conversations/$id'), headers: await _headers())
        .timeout(const Duration(seconds: 30));

    if (response.statusCode != 200) {
      throw const ApiException('Could not delete that chat.');
    }
  }

  /// Uploads a recording and returns the transcribed text.
  ///
  /// [language] is 'en', 'lo', or 'auto' — the server transcribes with both
  /// and keeps the higher-confidence result when auto, since Lao and English
  /// cannot be reliably auto-detected in one pass.
  Future<String> transcribe(String filePath, {String language = 'auto'}) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$baseUrl/api/v1/transcribe'),
    )
      ..headers.addAll({
        'X-Device-Token': await _token(),
        'Accept': 'application/json',
      })
      ..fields['language'] = language
      ..files.add(await http.MultipartFile.fromPath('audio', filePath));

    final streamed = await request.send().timeout(const Duration(seconds: 90));
    final response = await http.Response.fromStream(streamed);
    final body = jsonDecode(utf8.decode(response.bodyBytes)) as Map<String, dynamic>;

    if (response.statusCode != 200) {
      throw ApiException(body['message'] as String? ?? 'Could not transcribe the recording.');
    }

    return (body['text'] as String? ?? '').trim();
  }

  /// Returns spoken audio bytes for [text], ready to hand to the player.
  Future<List<int>> speech(String text) async {
    final response = await http
        .post(
          Uri.parse('$baseUrl/api/v1/tts'),
          headers: await _headers(),
          body: jsonEncode({'text': text}),
        )
        .timeout(const Duration(seconds: 120));

    if (response.statusCode != 200) {
      throw const ApiException('Could not generate speech.');
    }

    return response.bodyBytes;
  }

  /// Maps a file extension to the image subtype the server expects.
  String _imageSubtype(String path) => switch (path.split('.').last.toLowerCase()) {
        'png' => 'png',
        'webp' => 'webp',
        'gif' => 'gif',
        _ => 'jpeg',
      };

  Future<bool> health() async {
    try {
      final response = await http
          .get(Uri.parse('$baseUrl/api/v1/health'))
          .timeout(const Duration(seconds: 10));

      return response.statusCode == 200;
    } catch (_) {
      return false;
    }
  }
}

class ChatReply {
  const ChatReply({
    required this.conversationId,
    required this.reply,
    this.imageUrl,
  });

  final String conversationId;
  final String reply;
  final String? imageUrl;
}

class Conversation {
  const Conversation({required this.id, required this.title});

  final String id;
  final String title;

  factory Conversation.fromJson(Map<String, dynamic> json) => Conversation(
        id: json['id'] as String,
        title: (json['title'] as String?)?.trim().isNotEmpty == true
            ? json['title'] as String
            : 'New chat',
      );
}

class ChatMessage {
  const ChatMessage({required this.text, required this.fromUser, this.imageUrl});

  final String text;
  final bool fromUser;
  final String? imageUrl;

  factory ChatMessage.fromJson(Map<String, dynamic> json) => ChatMessage(
        text: json['content'] as String? ?? '',
        fromUser: json['role'] == 'user',
        imageUrl: json['image_url'] as String?,
      );
}

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
