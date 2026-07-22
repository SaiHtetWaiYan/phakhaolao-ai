import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phakhaolao_app/main.dart';

void main() {
  testWidgets('shows the empty state prompt on launch', (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    expect(find.text('Ask about Lao biodiversity'), findsOneWidget);
    expect(find.byType(TextField), findsOneWidget);
  });

  testWidgets('new chat action is disabled while there are no messages',
      (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    final button = tester.widget<IconButton>(
      find.widgetWithIcon(IconButton, Icons.add_comment_outlined),
    );

    expect(button.onPressed, isNull);
  });

  testWidgets('typed text stays in the composer until it is sent',
      (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    await tester.enterText(find.byType(TextField), 'How many champions?');
    await tester.pump();

    expect(find.text('How many champions?'), findsOneWidget);
  });
}
