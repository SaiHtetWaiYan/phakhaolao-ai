import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;
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

  /// Sends a message and returns the assistant's full reply.
  Future<ChatReply> send(
    String message, {
    String? conversationId,
    String? responseLanguage,
  }) async {
    final response = await http
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
    );
  }

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
  const ChatReply({required this.conversationId, required this.reply});

  final String conversationId;
  final String reply;
}

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
