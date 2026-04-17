class DashboardSummary {
  DashboardSummary({
    required this.totalHours,
    required this.remainingLeave,
    required this.loanAmount,
    required this.salaryCount,
    required this.noticeCount,
    required this.notices,
  });

  final String totalHours;
  final String remainingLeave;
  final String loanAmount;
  final int salaryCount;
  final int noticeCount;
  final List<String> notices;
}
