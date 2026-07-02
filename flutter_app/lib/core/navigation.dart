import 'package:flutter/material.dart';

import 'terminology.dart';
import 'theme.dart';

/// A single navigation entry. Used by both the desktop sidebar and the
/// phone "Mehr" sheet, so navigation is defined exactly once.
class NavItem {
  final String route;
  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final Color color;

  /// Shown in the phone bottom navigation bar.
  final bool primary;

  /// Numeric badge (0 = hidden).
  final int badge;

  /// Feature is not built yet (Phase 2) — rendered disabled with a hint.
  final bool comingSoon;

  const NavItem({
    required this.route,
    required this.icon,
    required this.selectedIcon,
    required this.label,
    this.color = AppTheme.primary,
    this.primary = false,
    this.badge = 0,
    this.comingSoon = false,
  });
}

/// A titled group of [NavItem]s.
class NavSection {
  final String title;
  final List<NavItem> items;
  const NavSection(this.title, this.items);
}

/// Badge counts surfaced in the navigation.
class NavBadges {
  final int unreadMessages;
  final int overdueInvoices;
  final int newIntakes;
  const NavBadges({
    this.unreadMessages = 0,
    this.overdueInvoices = 0,
    this.newIntakes = 0,
  });
}

/// Builds the grouped navigation model. Terminology and the trainer flag make
/// the same structure work for both practice types; the "Hundeschule" section
/// only appears for trainer accounts (items are placeholders until Phase 2).
List<NavSection> buildNavSections({
  required Terminology term,
  required bool isTrainer,
  NavBadges badges = const NavBadges(),
}) {
  return [
    const NavSection('Übersicht', [
      NavItem(
        route: '/dashboard',
        icon: Icons.dashboard_outlined,
        selectedIcon: Icons.dashboard_rounded,
        label: 'Dashboard',
        primary: true,
      ),
      NavItem(
        route: '/kalender',
        icon: Icons.calendar_month_outlined,
        selectedIcon: Icons.calendar_month_rounded,
        label: 'Kalender',
        color: AppTheme.tertiary,
        primary: true,
      ),
      NavItem(
        route: '/suche',
        icon: Icons.search_outlined,
        selectedIcon: Icons.search_rounded,
        label: 'Suche',
      ),
    ]),
    NavSection('Praxis', [
      NavItem(
        route: '/patienten',
        icon: Icons.pets_outlined,
        selectedIcon: Icons.pets_rounded,
        label: term.patientPlural,
        primary: true,
      ),
      NavItem(
        route: '/tierhalter',
        icon: Icons.person_outline_rounded,
        selectedIcon: Icons.person_rounded,
        label: term.ownerPlural,
        color: AppTheme.secondary,
      ),
      NavItem(
        route: '/anmeldungen',
        icon: Icons.assignment_ind_outlined,
        selectedIcon: Icons.assignment_ind_rounded,
        label: 'Anmeldungen',
        badge: badges.newIntakes,
      ),
      const NavItem(
        route: '/warteliste',
        icon: Icons.people_alt_outlined,
        selectedIcon: Icons.people_alt_rounded,
        label: 'Warteliste',
        color: AppTheme.warning,
      ),
      const NavItem(
        route: '/einladungen',
        icon: Icons.send_outlined,
        selectedIcon: Icons.send_rounded,
        label: 'Einladungen',
        color: AppTheme.secondary,
      ),
    ]),
    const NavSection('Behandlung', [
      NavItem(
        route: '/befunde',
        icon: Icons.description_outlined,
        selectedIcon: Icons.description_rounded,
        label: 'Befundbögen',
        color: AppTheme.secondary,
      ),
      NavItem(
        route: '/hausaufgaben',
        icon: Icons.assignment_outlined,
        selectedIcon: Icons.assignment_rounded,
        label: 'Hausaufgaben',
      ),
      NavItem(
        route: '/behandlungsarten',
        icon: Icons.category_outlined,
        selectedIcon: Icons.category_rounded,
        label: 'Behandlungsarten',
        color: AppTheme.tertiary,
      ),
      NavItem(
        route: '/tcp',
        icon: Icons.healing_outlined,
        selectedIcon: Icons.healing_rounded,
        label: 'Therapy Care',
      ),
    ]),
    NavSection('Finanzen', [
      const NavItem(
        route: '/rechnungen',
        icon: Icons.receipt_long_outlined,
        selectedIcon: Icons.receipt_long_rounded,
        label: 'Rechnungen',
      ),
      NavItem(
        route: '/mahnungen',
        icon: Icons.warning_amber_outlined,
        selectedIcon: Icons.warning_amber_rounded,
        label: 'Mahnungen',
        color: AppTheme.danger,
        badge: badges.overdueInvoices,
      ),
      const NavItem(
        route: '/steuerexport',
        icon: Icons.account_balance_outlined,
        selectedIcon: Icons.account_balance_rounded,
        label: 'Steuerexport',
        color: AppTheme.secondary,
      ),
    ]),
    NavSection('Kommunikation', [
      NavItem(
        route: '/nachrichten',
        icon: Icons.chat_outlined,
        selectedIcon: Icons.chat_rounded,
        label: 'Nachrichten',
        primary: true,
        badge: badges.unreadMessages,
      ),
      const NavItem(
        route: '/mailbox',
        icon: Icons.mail_outline_rounded,
        selectedIcon: Icons.mail_rounded,
        label: 'Mailbox',
        color: AppTheme.tertiary,
      ),
      const NavItem(
        route: '/portal-admin',
        icon: Icons.home_work_outlined,
        selectedIcon: Icons.home_work_rounded,
        label: 'Portal Admin',
        color: AppTheme.tertiary,
      ),
    ]),
    // Trainer-only module (Hundeschule) — implemented in Phase 2.
    if (isTrainer)
      const NavSection('Hundeschule', [
        NavItem(
          route: '/kurse',
          icon: Icons.school_outlined,
          selectedIcon: Icons.school_rounded,
          label: 'Kurse',
          color: AppTheme.tertiary,
        ),
        NavItem(
          route: '/anwesenheit',
          icon: Icons.checklist_outlined,
          selectedIcon: Icons.checklist_rounded,
          label: 'Anwesenheit',
          color: AppTheme.tertiary,
        ),
        NavItem(
          route: '/pakete',
          icon: Icons.confirmation_number_outlined,
          selectedIcon: Icons.confirmation_number_rounded,
          label: 'Pakete',
          color: AppTheme.tertiary,
        ),
        NavItem(
          route: '/interessenten',
          icon: Icons.person_add_alt_outlined,
          selectedIcon: Icons.person_add_alt_1_rounded,
          label: 'Interessenten',
          color: AppTheme.tertiary,
        ),
        NavItem(
          route: '/trainingsplaene',
          icon: Icons.fitness_center_outlined,
          selectedIcon: Icons.fitness_center_rounded,
          label: 'Trainingspläne',
          color: AppTheme.tertiary,
        ),
      ]),
    const NavSection('Konto', [
      NavItem(
        route: '/profil',
        icon: Icons.account_circle_outlined,
        selectedIcon: Icons.account_circle_rounded,
        label: 'Mein Profil',
      ),
      NavItem(
        route: '/einstellungen',
        icon: Icons.settings_outlined,
        selectedIcon: Icons.settings_rounded,
        label: 'Einstellungen',
        color: AppTheme.tertiary,
      ),
    ]),
  ];
}

/// Flattened list of all items (handy for lookups).
List<NavItem> flattenNav(List<NavSection> sections) =>
    [for (final s in sections) ...s.items];

/// Items shown in the phone bottom navigation bar.
List<NavItem> primaryNavItems(List<NavSection> sections) =>
    [for (final i in flattenNav(sections)) if (i.primary) i];
