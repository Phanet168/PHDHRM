class HomeNotificationItem {
  const HomeNotificationItem({
    required this.id,
    required this.title,
    required this.description,
    required this.meta,
    required this.isUnread,
    required this.source,
    required this.typeLabel,
    required this.dateLabel,
    required this.audienceLabel,
    required this.contextLabel,
    required this.stepName,
    this.sentAt,
    this.readAt,
    this.link,
  });

  final String id;
  final String title;
  final String description;
  final String meta;
  final bool isUnread;
  final String source;
  final String typeLabel;
  final String dateLabel;
  final String audienceLabel;
  final String contextLabel;
  final String stepName;
  final String? sentAt;
  final String? readAt;
  final String? link;
}

class HomeNotificationPageData {
  const HomeNotificationPageData({
    required this.items,
    required this.unreadCount,
  });

  final List<HomeNotificationItem> items;
  final int unreadCount;
}
