class HomeNotificationItem {
  const HomeNotificationItem({
    required this.id,
    required this.title,
    required this.description,
    required this.meta,
    required this.isUnread,
    this.sentAt,
    this.readAt,
  });

  final int id;
  final String title;
  final String description;
  final String meta;
  final bool isUnread;
  final String? sentAt;
  final String? readAt;
}

class HomeNotificationPageData {
  const HomeNotificationPageData({
    required this.items,
    required this.unreadCount,
  });

  final List<HomeNotificationItem> items;
  final int unreadCount;
}
