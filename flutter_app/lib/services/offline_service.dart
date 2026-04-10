import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:sqflite/sqflite.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';
import 'package:path/path.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'api_service.dart';

class OfflineService extends ChangeNotifier {
  static const _dbName = 'therapano_offline.db';
  static const _dbVersion = 1;
  static const _lastSyncKey = 'last_sync_timestamp';
  static const _offlineLimitDays = 14;
  
  Database? _db;
  bool _isOnline = true;
  DateTime? _lastSync;
  DateTime? _lastOnlineCheck;
  bool _isInitialized = false;

  bool get isOnline => _isOnline;
  bool get isInitialized => _isInitialized;
  DateTime? get lastSync => _lastSync;
  bool get isOfflineLimitExceeded {
    if (_lastOnlineCheck == null) return false;
    final daysSinceOnline = DateTime.now().difference(_lastOnlineCheck!).inDays;
    return daysSinceOnline > _offlineLimitDays;
  }

  Future<void> init() async {
    if (_isInitialized) return;

    // Initialize FFI for Windows/Desktop
    if (Platform.isWindows || Platform.isLinux || Platform.isMacOS) {
      sqfliteFfiInit();
      databaseFactory = databaseFactoryFfi;
    }

    await _initDatabase();
    await _loadLastSync();
    await _checkConnectivity();
    _isInitialized = true;
    notifyListeners();

    // Periodic connectivity check
    Timer.periodic(const Duration(minutes: 1), (_) async {
      await _checkConnectivity();
    });
  }

  Future<void> _initDatabase() async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, _dbName);
    
    _db = await openDatabase(
      path,
      version: _dbVersion,
      onCreate: _onCreate,
      onUpgrade: _onUpgrade,
    );
  }

  Future<void> _onCreate(Database db, int version) async {
    // Patients table
    await db.execute('''
      CREATE TABLE patients (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        species TEXT,
        breed TEXT,
        gender TEXT,
        birth_date TEXT,
        owner_id INTEGER,
        photo_url TEXT,
        created_at TEXT,
        updated_at TEXT,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Owners table
    await db.execute('''
      CREATE TABLE owners (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT,
        phone TEXT,
        address TEXT,
        city TEXT,
        zip TEXT,
        created_at TEXT,
        updated_at TEXT,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Invoices table
    await db.execute('''
      CREATE TABLE invoices (
        id INTEGER PRIMARY KEY,
        invoice_number TEXT NOT NULL,
        owner_id INTEGER,
        patient_id INTEGER,
        total_gross REAL,
        total_net REAL,
        status TEXT,
        issue_date TEXT,
        due_date TEXT,
        created_at TEXT,
        updated_at TEXT,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Appointments table
    await db.execute('''
      CREATE TABLE appointments (
        id INTEGER PRIMARY KEY,
        patient_id INTEGER,
        owner_id INTEGER,
        title TEXT NOT NULL,
        appointment_date TEXT,
        time TEXT,
        duration INTEGER,
        status TEXT,
        notes TEXT,
        created_at TEXT,
        updated_at TEXT,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Messages table
    await db.execute('''
      CREATE TABLE messages (
        id INTEGER PRIMARY KEY,
        thread_id INTEGER,
        subject TEXT,
        message TEXT,
        sender_id INTEGER,
        receiver_id INTEGER,
        is_read INTEGER DEFAULT 0,
        created_at TEXT,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Sync queue table
    await db.execute('''
      CREATE TABLE sync_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        table_name TEXT NOT NULL,
        record_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        data TEXT NOT NULL,
        created_at TEXT NOT NULL
      )
    ''');
  }

  Future<void> _onUpgrade(Database db, int oldVersion, int newVersion) async {
    // Handle database upgrades
  }

  Future<void> _loadLastSync() async {
    final prefs = await SharedPreferences.getInstance();
    final timestamp = prefs.getInt(_lastSyncKey);
    if (timestamp != null) {
      _lastSync = DateTime.fromMillisecondsSinceEpoch(timestamp);
    }
  }

  Future<void> _saveLastSync() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_lastSyncKey, DateTime.now().millisecondsSinceEpoch);
    _lastSync = DateTime.now();
    notifyListeners();
  }

  Future<void> _checkConnectivity() async {
    try {
      final response = await http
          .get(Uri.parse('${ApiService.baseUrl}/api/mobile/ping'))
          .timeout(const Duration(seconds: 5));
      
      final wasOffline = !_isOnline;
      _isOnline = response.statusCode == 200;
      
      if (_isOnline) {
        _lastOnlineCheck = DateTime.now();
        if (wasOffline) {
          // Sync when coming back online
          await sync();
        }
      }
      
      notifyListeners();
    } catch (_) {
      _isOnline = false;
      notifyListeners();
    }
  }

  // ── DATA STORAGE METHODS ──

  Future<void> savePatient(Map<String, dynamic> patient) async {
    if (_db == null) return;
    
    await _db!.insert(
      'patients',
      {
        ...patient,
        'synced': _isOnline ? 1 : 0,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    
    if (!_isOnline) {
      await _addToSyncQueue('patients', patient['id'] as int, 'update', patient);
    }
  }

  Future<void> saveOwner(Map<String, dynamic> owner) async {
    if (_db == null) return;
    
    await _db!.insert(
      'owners',
      {
        ...owner,
        'synced': _isOnline ? 1 : 0,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    
    if (!_isOnline) {
      await _addToSyncQueue('owners', owner['id'] as int, 'update', owner);
    }
  }

  Future<void> saveInvoice(Map<String, dynamic> invoice) async {
    if (_db == null) return;
    
    await _db!.insert(
      'invoices',
      {
        ...invoice,
        'synced': _isOnline ? 1 : 0,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    
    if (!_isOnline) {
      await _addToSyncQueue('invoices', invoice['id'] as int, 'update', invoice);
    }
  }

  Future<void> saveAppointment(Map<String, dynamic> appointment) async {
    if (_db == null) return;
    
    await _db!.insert(
      'appointments',
      {
        ...appointment,
        'synced': _isOnline ? 1 : 0,
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
    
    if (!_isOnline) {
      await _addToSyncQueue('appointments', appointment['id'] as int, 'update', appointment);
    }
  }

  // ── DATA RETRIEVAL METHODS ──

  Future<List<Map<String, dynamic>>> getPatients() async {
    if (_db == null) return [];
    final results = await _db!.query('patients', orderBy: 'name');
    return results;
  }

  Future<List<Map<String, dynamic>>> getOwners() async {
    if (_db == null) return [];
    final results = await _db!.query('owners', orderBy: 'name');
    return results;
  }

  Future<List<Map<String, dynamic>>> getInvoices() async {
    if (_db == null) return [];
    final results = await _db!.query('invoices', orderBy: 'issue_date DESC');
    return results;
  }

  Future<List<Map<String, dynamic>>> getAppointments() async {
    if (_db == null) return [];
    final results = await _db!.query('appointments', orderBy: 'appointment_date DESC');
    return results;
  }

  // ── SYNC METHODS ──

  Future<void> _addToSyncQueue(String tableName, int recordId, String action, Map<String, dynamic> data) async {
    if (_db == null) return;
    
    await _db!.insert('sync_queue', {
      'table_name': tableName,
      'record_id': recordId,
      'action': action,
      'data': jsonEncode(data),
      'created_at': DateTime.now().toIso8601String(),
    });
  }

  Future<void> sync() async {
    if (!_isOnline || _db == null) return;
    
    try {
      // Process sync queue
      final queueItems = await _db!.query('sync_queue');
      
      for (final item in queueItems) {
        final tableName = item['table_name'] as String;
        final recordId = item['record_id'] as int;
        final action = item['action'] as String;
        final data = jsonDecode(item['data'] as String) as Map<String, dynamic>;
        
        await _syncItem(tableName, recordId, action, data);
        
        // Remove from queue
        await _db!.delete('sync_queue', where: 'id = ?', whereArgs: [item['id']]);
      }
      
      // Fetch fresh data from server
      await _syncFromServer();
      
      await _saveLastSync();
    } catch (_) {
      // Sync failed, will retry later
    }
  }

  Future<void> _syncItem(String tableName, int recordId, String action, Map<String, dynamic> data) async {
    final token = await ApiService.getToken();
    if (token == null) return;

    final headers = {
      'Authorization': 'Bearer $token',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };
    final base = ApiService.baseUrl;
    final body = jsonEncode(data);
    const timeout = Duration(seconds: 30);

    switch (tableName) {
      case 'patients':
        if (action == 'update') {
          await http
              .put(Uri.parse('$base/api/mobile/patients/$recordId'), headers: headers, body: body)
              .timeout(timeout);
        }
        break;
      case 'owners':
        if (action == 'update') {
          await http
              .put(Uri.parse('$base/api/mobile/owners/$recordId'), headers: headers, body: body)
              .timeout(timeout);
        }
        break;
      case 'invoices':
        if (action == 'update') {
          await http
              .put(Uri.parse('$base/api/mobile/invoices/$recordId'), headers: headers, body: body)
              .timeout(timeout);
        }
        break;
      case 'appointments':
        if (action == 'update') {
          await http
              .put(Uri.parse('$base/api/mobile/appointments/$recordId'), headers: headers, body: body)
              .timeout(timeout);
        }
        break;
    }
  }


  Future<void> _syncFromServer() async {
    final api = ApiService();
    final token = await ApiService.getToken();
    
    if (token == null) return;
    
    // Sync patients
    try {
      final patients = await api.patients();
      for (final patient in patients['items'] as List) {
        await savePatient(patient as Map<String, dynamic>);
      }
    } catch (_) {}
    
    // Sync owners
    try {
      final owners = await api.owners();
      for (final owner in owners['items'] as List) {
        await saveOwner(owner as Map<String, dynamic>);
      }
    } catch (_) {}
    
    // Sync invoices
    try {
      final invoices = await api.invoices();
      for (final invoice in invoices['items'] as List) {
        await saveInvoice(invoice as Map<String, dynamic>);
      }
    } catch (_) {}
    
    // Sync appointments
    try {
      final appointments = await api.appointments();
      for (final appointment in appointments) {
        await saveAppointment(appointment as Map<String, dynamic>);
      }
    } catch (_) {}
  }

  Future<void> clearDatabase() async {
    if (_db == null) return;
    await _db!.close();
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, _dbName);
    await deleteDatabase(path);
    await _initDatabase();
  }
}
