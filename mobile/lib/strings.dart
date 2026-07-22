/// Interface strings, matching the wording used on the website.
///
/// The reply-language setting drives these too: choosing ລາວ switches the whole
/// interface to Lao, not just the assistant's replies.
class Strings {
  const Strings._(this.map);

  final Map<String, String> map;

  static const _en = <String, String>{
    'new_chat': 'New chat',
    'recent': 'Recent',
    'welcome_title': 'How can I help you today?',
    'welcome_subtitle':
        'Ask about Laos plants, animals, uses, habitats, and local species data '
            'from the PhaKhaoLao knowledge base.',
    'placeholder': 'Message PhaKhaoLao AI…',
    'listening': 'Listening…',
    'transcribing': 'Transcribing…',
    'listen': 'Listen',
    'stop': 'Stop',
    'copy': 'Copy',
    'copied': 'Copied',
    'delete': 'Delete',
    'cancel': 'Cancel',
    'delete_title': 'Delete conversation?',
    'delete_body':
        'This will permanently delete this conversation and all messages.',
    'no_chats': 'No chats yet.',
    'chats_failed': 'Could not load your chats.',
    'open_failed': 'Could not open that chat.',
    'delete_failed': 'Could not delete that chat.',
    'reply_language': 'Reply language',
    'language_auto': 'Auto',
    'attach': 'Attach a photo',
    'take_photo': 'Take a photo',
    'choose_photo': 'Choose from gallery',
    'remove': 'Remove',
    'image_failed': 'Could not open that photo.',
    'appearance': 'Appearance',
    'theme_system': 'System',
    'theme_light': 'Light',
    'theme_dark': 'Dark',
    'network_error': 'Could not reach the server. Check your connection.',
    'too_short': 'Recording was too short.',
    'not_heard': 'Could not hear anything. Please try again.',
    'transcribe_failed': 'Transcription failed.',
    'mic_permission': 'Microphone permission is required for voice input.',
    'audio_failed': 'Could not play audio.',
  };

  static const _lo = <String, String>{
    'new_chat': 'ສ້າງການສົນທະນາໃໝ່',
    'recent': 'ຫຼ້າສຸດ',
    'welcome_title': 'ມື້ນີ້ຂ້ອຍຊ່ວຍຫຍັງທ່ານໄດ້ແດ່?',
    'welcome_subtitle':
        'ຖາມກ່ຽວກັບພືດ, ສັດ, ການນຳໃຊ້, ຖິ່ນທີ່ຢູ່ ແລະ ຂໍ້ມູນຊະນິດພັນທ້ອງຖິ່ນຂອງລາວ '
            'ຈາກຖານຄວາມຮູ້ PhaKhaoLao.',
    'placeholder': 'ພິມຂໍ້ຄວາມຫາ PhaKhaoLao AI...',
    'listening': 'ກຳລັງຟັງ...',
    'transcribing': 'ກຳລັງແປງສຽງເປັນຂໍ້ຄວາມ...',
    'listen': 'ຟັງ',
    'stop': 'ຢຸດ',
    'copy': 'ສຳເນົາ',
    'copied': 'ສຳເນົາແລ້ວ',
    'delete': 'ລຶບ',
    'cancel': 'ຍົກເລີກ',
    'delete_title': 'ລຶບການສົນທະນາ?',
    'delete_body': 'ນີ້ຈະລຶບການສົນທະນານີ້ ແລະ ຂໍ້ຄວາມທັງໝົດຢ່າງຖາວອນ.',
    'no_chats': 'ຍັງບໍ່ມີການສົນທະນາ.',
    'chats_failed': 'ບໍ່ສາມາດໂຫຼດການສົນທະນາໄດ້.',
    'open_failed': 'ບໍ່ສາມາດເປີດການສົນທະນານີ້ໄດ້.',
    'delete_failed': 'ບໍ່ສາມາດລຶບການສົນທະນານີ້ໄດ້.',
    'reply_language': 'ພາສາຂອງຄຳຕອບ',
    'language_auto': 'ອັດຕະໂນມັດ',
    'attach': 'ແນບຮູບພາບ',
    'take_photo': 'ຖ່າຍຮູບ',
    'choose_photo': 'ເລືອກຈາກຄັງຮູບ',
    'remove': 'ເອົາອອກ',
    'image_failed': 'ບໍ່ສາມາດເປີດຮູບນີ້ໄດ້.',
    'appearance': 'ຮູບແບບການສະແດງຜົນ',
    'theme_system': 'ຕາມລະບົບ',
    'theme_light': 'ສະຫວ່າງ',
    'theme_dark': 'ມືດ',
    'network_error': 'ບໍ່ສາມາດເຊື່ອມຕໍ່ຫາເຊີບເວີໄດ້. ກະລຸນາກວດສອບອິນເຕີເນັດ.',
    'too_short': 'ການບັນທຶກສຽງສັ້ນເກີນໄປ.',
    'not_heard': 'ບໍ່ໄດ້ຍິນສຽງ. ກະລຸນາລອງໃໝ່.',
    'transcribe_failed': 'ການແປງສຽງເປັນຂໍ້ຄວາມລົ້ມເຫຼວ.',
    'mic_permission': 'ຕ້ອງອະນຸຍາດການໃຊ້ໄມໂຄຣໂຟນເພື່ອປ້ອນດ້ວຍສຽງ.',
    'audio_failed': 'ບໍ່ສາມາດຫຼິ້ນສຽງໄດ້.',
  };

  /// Lao interface for the Lao setting; English for English and Auto.
  factory Strings.of(String language) =>
      Strings._(language == 'lo' ? _lo : _en);

  String call(String key) => map[key] ?? key;
}
