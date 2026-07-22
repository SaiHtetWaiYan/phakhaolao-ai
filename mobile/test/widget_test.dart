import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:phakhaolao_app/main.dart';

void main() {
  testWidgets('shows the empty state prompt on launch', (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    expect(find.text('How can I help you today?'), findsOneWidget);
    expect(find.byType(TextField), findsOneWidget);
  });

  testWidgets('new chat action is disabled while there are no messages',
      (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    final button = tester.widget<IconButton>(
      find.widgetWithIcon(IconButton, Icons.edit_square),
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

  // A null popup-menu result is indistinguishable from dismissing the menu, so
  // every language option must carry a non-null value or it can never be
  // selected.
  testWidgets('every language option is selectable', (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    await tester.tap(find.byIcon(Icons.translate));
    await tester.pumpAndSettle();

    final items = tester
        .widgetList<PopupMenuItem<String>>(find.byType(PopupMenuItem<String>))
        .toList();

    expect(items, hasLength(3));
    expect(items.every((item) => item.value != null), isTrue);
    expect(items.map((item) => item.value), containsAll(['auto', 'en', 'lo']));
  });

  testWidgets('choosing Lao keeps the menu selection', (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    await tester.tap(find.byIcon(Icons.translate));
    await tester.pumpAndSettle();
    await tester.tap(find.text('ລາວ'));
    await tester.pumpAndSettle();

    await tester.tap(find.byIcon(Icons.translate));
    await tester.pumpAndSettle();

    final button = tester.widget<PopupMenuButton<String>>(
      find.byType(PopupMenuButton<String>),
    );

    expect(button.initialValue, 'lo');
  });

  testWidgets('opens the history drawer', (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    final state = tester.state<ScaffoldState>(find.byType(Scaffold));
    state.openDrawer();
    await tester.pump();

    expect(find.text('New chat'), findsOneWidget);
  });

  testWidgets('switching to Lao translates the interface, not just replies',
      (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    expect(find.text('How can I help you today?'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.translate));
    await tester.pumpAndSettle();
    await tester.tap(find.text('ລາວ'));
    await tester.pumpAndSettle();

    expect(find.text('ມື້ນີ້ຂ້ອຍຊ່ວຍຫຍັງທ່ານໄດ້ແດ່?'), findsOneWidget);
    expect(find.text('How can I help you today?'), findsNothing);
  });

  testWidgets('the Lao interface reverts when English is chosen',
      (tester) async {
    await tester.pumpWidget(const PhaKhaoLaoApp());

    await tester.tap(find.byIcon(Icons.translate));
    await tester.pumpAndSettle();
    await tester.tap(find.text('ລາວ'));
    await tester.pumpAndSettle();

    await tester.tap(find.byIcon(Icons.translate));
    await tester.pumpAndSettle();
    await tester.tap(find.text('English'));
    await tester.pumpAndSettle();

    expect(find.text('How can I help you today?'), findsOneWidget);
  });
}
