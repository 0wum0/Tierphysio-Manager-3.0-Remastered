import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class TcpFeedbackScreen extends StatefulWidget {
  final int patientId;
  const TcpFeedbackScreen({super.key, required this.patientId});

  @override
  State<TcpFeedbackScreen> createState() => _TcpFeedbackScreenState();
}

class _TcpFeedbackScreenState extends State<TcpFeedbackScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _feedback = [];
  List<Map<String, dynamic>> _problematic = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    try {
      final results = await Future.wait([
        _api.tcpFeedbackList(widget.patientId).then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
        _api.tcpFeedbackProblematic().then((l) => l.map((e) => Map<String, dynamic>.from(e as Map)).toList()),
      ]);
      setState(() {
        _feedback = results[0];
        _problematic = results[1];
        _loading = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Übungs-Feedback'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text('Problematische Übungen', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ..._problematic.map((p) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(p['exercise_title'] as String? ?? 'Übung'),
                      subtitle: Text(p['patient_name'] as String? ?? ''),
                      trailing: Icon(Icons.warning, color: Colors.orange.shade700),
                    ),
                  )),
                  const SizedBox(height: 24),
                  const Text('Feedback-Verlauf', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  ..._feedback.map((f) => Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(f['exercise_title'] as String? ?? 'Übung'),
                      subtitle: Text('${f['rating']}/5 - ${f['notes'] as String? ?? ''}'),
                      trailing: Text(f['feedback_date'] as String? ?? ''),
                    ),
                  )),
                ],
              ),
            ),
    );
  }
}
