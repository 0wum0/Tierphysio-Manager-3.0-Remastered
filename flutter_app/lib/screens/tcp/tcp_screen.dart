import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class TcpScreen extends StatefulWidget {
  const TcpScreen({super.key});

  @override
  State<TcpScreen> createState() => _TcpScreenState();
}

class _TcpScreenState extends State<TcpScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final ApiService _api = ApiService();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 6, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Therapy Care Pro'),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: const [
            Tab(text: 'Fortschritt'),
            Tab(text: 'Feedback'),
            Tab(text: 'Berichte'),
            Tab(text: 'Bibliothek'),
            Tab(text: 'Naturheilkunde'),
            Tab(text: 'Erinnerungen'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: const [
          _TcpProgressTab(),
          _TcpFeedbackTab(),
          _TcpReportsTab(),
          _TcpLibraryTab(),
          _TcpNaturalTab(),
          _TcpRemindersTab(),
        ],
      ),
    );
  }
}

class _TcpProgressTab extends StatelessWidget {
  const _TcpProgressTab();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Fortschritt-Tracking'),
    );
  }
}

class _TcpFeedbackTab extends StatelessWidget {
  const _TcpFeedbackTab();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Übungs-Feedback'),
    );
  }
}

class _TcpReportsTab extends StatelessWidget {
  const _TcpReportsTab();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Therapie-Berichte'),
    );
  }
}

class _TcpLibraryTab extends StatelessWidget {
  const _TcpLibraryTab();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Übungs-Bibliothek'),
    );
  }
}

class _TcpNaturalTab extends StatelessWidget {
  const _TcpNaturalTab();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Naturheilkunde'),
    );
  }
}

class _TcpRemindersTab extends StatelessWidget {
  const _TcpRemindersTab();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Text('Erinnerungs-Warteschlange'),
    );
  }
}
