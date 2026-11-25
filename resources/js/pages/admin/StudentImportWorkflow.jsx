import React, { useState, useEffect } from 'react';
import api, { downloadFile } from '../../api/axios';
import {
  UploadCloud,
  FileSpreadsheet,
  Download,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
  ArrowLeft,
  KeyRound,
  FileCheck,
  RefreshCw,
  Sparkles
} from 'lucide-react';

export default function StudentImportWorkflow() {
  const [currentStep, setCurrentStep] = useState(1);
  const [exams, setExams] = useState([]);
  const [selectedExamId, setSelectedExamId] = useState('');
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Preview & Mapping
  const [previewData, setPreviewData] = useState(null);
  const [columnMapping, setColumnMapping] = useState({
    name: 'Student Name',
    phone: 'Phone Number',
    email: 'Email',
    institution: 'Institution',
    hsc_batch: 'HSC Batch',
    gender: 'Gender',
    address: 'Address',
    center: 'Center Code',
    room_number: 'Room Number',
    seat_number: 'Seat Number',
    guardian_phone: 'Guardian Phone',
  });

  // Final confirmation result
  const [importResult, setImportResult] = useState(null);

  useEffect(() => {
    fetchExams();
  }, []);

  const fetchExams = async () => {
    try {
      const res = await api.get('/admin/exams?per_page=100');
      if (res.data.success) {
        setExams(res.data.data.data);
        if (res.data.data.data.length > 0) {
          setSelectedExamId(res.data.data.data[0].id);
        }
      }
    } catch (e) {}
  };

  const handleDownloadTemplate = async () => {
    try {
      await downloadFile('/admin/students/import/template', 'student_import_template.csv');
    } catch (e) {
      // Instant Client-side Fallback
      const csv = "Student Name,Phone Number,Email,Institution,HSC Batch,Gender,Address,Center Code,Room Number,Seat Number,Guardian Phone\n" +
        "Anika Tabassum,01712345678,anika@example.com,Viqarunnisa Noon College,HSC-2026,female,\"Dhanmondi, Dhaka\",DHK-01,Room 101,A-12,01700112233\n" +
        "Tanvir Hasan,01812345678,tanvir@example.com,Notre Dame College,HSC-2026,male,\"Mirpur, Dhaka\",DHK-02,Auditorium A,B-05,01800112233";
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', 'student_import_template.csv');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    }
  };

  const handleFileChange = (e) => {
    const selected = e.target.files[0];
    if (selected) {
      setFile(selected);
      setError('');
    }
  };

  // Step 2 -> Step 3 & 4 (Upload & Preview)
  const handleUploadAndPreview = async () => {
    if (!selectedExamId) {
      setError('Please select an examination first.');
      return;
    }
    if (!file) {
      setError('Please select a spreadsheet file (.xlsx, .xls, .csv).');
      return;
    }

    setLoading(true);
    setError('');

    const formData = new FormData();
    formData.append('file', file);
    formData.append('exam_id', selectedExamId);
    formData.append('mapping', JSON.stringify(columnMapping));

    try {
      const res = await api.post('/admin/students/import/preview', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      if (res.data.success) {
        setPreviewData(res.data.data);
        setCurrentStep(3);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to parse and validate spreadsheet.');
    } finally {
      setLoading(false);
    }
  };

  // Re-preview if mapping changes
  const handleRevalidate = async () => {
    handleUploadAndPreview();
  };

  // Step 4 -> Step 5 (Confirm & Execute Import)
  const handleConfirmImport = async () => {
    if (!previewData?.cache_token) return;

    setLoading(true);
    setError('');

    try {
      const res = await api.post('/admin/students/import/confirm', {
        cache_token: previewData.cache_token,
        exam_id: selectedExamId,
        mapping: columnMapping,
      });

      if (res.data.success) {
        setImportResult(res.data.data);
        setCurrentStep(4);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Error executing import transaction.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Excel Student Bulk Registration</h1>
          <p className="text-xs text-slate-500 mt-1">Multi-step spreadsheet ingestion with phone canonicalization and temporary credential generation.</p>
        </div>

        <button
          onClick={handleDownloadTemplate}
          className="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold uppercase tracking-wider shadow-xs transition-colors cursor-pointer"
        >
          <Download className="w-4 h-4 text-blue-600" /> Download Import Template (.CSV)
        </button>
      </div>

      {/* Stepper Wizard Bar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
        <div className="grid grid-cols-4 gap-2 text-center text-xs">
          <div className={`p-2 rounded-xl font-bold ${currentStep === 1 ? 'bg-blue-50 text-blue-700 border border-blue-200' : currentStep > 1 ? 'text-emerald-600' : 'text-slate-400'}`}>
            1. Select Exam
          </div>
          <div className={`p-2 rounded-xl font-bold ${currentStep === 2 ? 'bg-blue-50 text-blue-700 border border-blue-200' : currentStep > 2 ? 'text-emerald-600' : 'text-slate-400'}`}>
            2. Upload Spreadsheet
          </div>
          <div className={`p-2 rounded-xl font-bold ${currentStep === 3 ? 'bg-blue-50 text-blue-700 border border-blue-200' : currentStep > 3 ? 'text-emerald-600' : 'text-slate-400'}`}>
            3. Map & Validate
          </div>
          <div className={`p-2 rounded-xl font-bold ${currentStep === 4 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-400'}`}>
            4. Execution Summary
          </div>
        </div>
      </div>

      {error && (
        <div className="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs font-semibold text-rose-700 flex items-center gap-3">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* STEP 1: Select Exam & STEP 2: Upload File */}
      {currentStep <= 2 && (
        <div className="bg-white p-6 sm:p-8 rounded-2xl border border-slate-200 shadow-xs space-y-6">
          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-2">
              Step 1: Select Target Examination *
            </label>
            <select
              value={selectedExamId}
              onChange={(e) => setSelectedExamId(e.target.value)}
              className="w-full sm:w-96 px-4 py-2.5 text-xs font-semibold border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-blue-600"
            >
              {exams.map(e => (
                <option key={e.id} value={e.id}>{e.code} - {e.title} ({e.exam_date})</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-700 mb-2">
              Step 2: Upload Excel (.XLSX, .XLS) or CSV File *
            </label>
            <div className="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center hover:border-blue-400 transition-colors bg-slate-50/50">
              <UploadCloud className="w-12 h-12 text-blue-600 mx-auto mb-3" />
              <p className="text-sm font-bold text-slate-800">
                {file ? file.name : 'Drag & drop your student spreadsheet here'}
              </p>
              <p className="text-xs text-slate-400 mt-1">Supports standard Excel and CSV format up to 10MB</p>

              <label className="mt-4 inline-block px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold uppercase tracking-wider rounded-xl cursor-pointer shadow-sm">
                Browse Files
                <input
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  onChange={handleFileChange}
                  className="hidden"
                />
              </label>
            </div>
          </div>

          <div className="flex justify-end pt-4 border-t border-slate-100">
            <button
              onClick={handleUploadAndPreview}
              disabled={loading || !file || !selectedExamId}
              className="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider disabled:opacity-50 transition-colors cursor-pointer"
            >
              {loading ? 'Validating Spreadsheet...' : 'Upload & Proceed to Mapping'}
              <ArrowRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {/* STEP 3: Column Mapping & Validation Preview */}
      {currentStep === 3 && previewData && (
        <div className="space-y-6">
          {/* Diagnostic Metrics Cards */}
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div className="bg-white p-4 rounded-xl border border-slate-200">
              <span className="text-[10px] uppercase font-bold text-slate-400 block">Total Rows</span>
              <strong className="text-xl font-black text-slate-900">{previewData.total_rows}</strong>
            </div>

            <div className="bg-white p-4 rounded-xl border border-emerald-200 bg-emerald-50/20">
              <span className="text-[10px] uppercase font-bold text-emerald-600 block">Valid New Students</span>
              <strong className="text-xl font-black text-emerald-600">{previewData.valid_new_count}</strong>
            </div>

            <div className="bg-white p-4 rounded-xl border border-blue-200 bg-blue-50/20">
              <span className="text-[10px] uppercase font-bold text-blue-600 block">Reused Existing Profiles</span>
              <strong className="text-xl font-black text-blue-600">{previewData.existing_students_count}</strong>
            </div>

            <div className="bg-white p-4 rounded-xl border border-amber-200 bg-amber-50/20">
              <span className="text-[10px] uppercase font-bold text-amber-600 block">Duplicate in Exam</span>
              <strong className="text-xl font-black text-amber-600">{previewData.duplicate_in_exam_count}</strong>
            </div>

            <div className="bg-white p-4 rounded-xl border border-rose-200 bg-rose-50/20">
              <span className="text-[10px] uppercase font-bold text-rose-600 block">Errors / Invalid Rows</span>
              <strong className="text-xl font-black text-rose-600">{previewData.error_rows_count}</strong>
            </div>
          </div>

          {/* Validation Warnings list if any */}
          {previewData.errors.length > 0 && (
            <div className="bg-rose-50 border border-rose-200 p-4 rounded-2xl space-y-2">
              <h4 className="text-xs font-bold text-rose-900 uppercase tracking-wider flex items-center gap-1.5">
                <AlertTriangle className="w-4 h-4 text-rose-600" />
                Detected Validation Diagnostics ({previewData.errors.length} Rows)
              </h4>
              <div className="max-h-40 overflow-y-auto divide-y divide-rose-100 text-[11px] text-rose-800">
                {previewData.errors.map((err, idx) => (
                  <div key={idx} className="py-1 flex items-start justify-between gap-2">
                    <span>Row {err.row}: <strong>{err.name || 'Unnamed'}</strong> ({err.phone})</span>
                    <span className="font-semibold text-rose-600">{err.messages.join('; ')}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Data Preview Table */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div className="p-4 border-b border-slate-100 flex items-center justify-between">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                Sample Record Ingestion Preview (First 25 Rows)
              </h3>
              <span className="text-[11px] text-slate-400">Bangladeshi Phone & Reg No Normalized</span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200">
                    <th className="py-2.5 px-3">Row</th>
                    <th className="py-2.5 px-3">Candidate Name</th>
                    <th className="py-2.5 px-3">Phone Input</th>
                    <th className="py-2.5 px-3">Canonical Phone & Reg No</th>
                    <th className="py-2.5 px-3">Center & Seat</th>
                    <th className="py-2.5 px-3">Status Assessment</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {previewData.preview.map((row, idx) => (
                    <tr key={idx} className={row.is_valid ? 'hover:bg-slate-50' : 'bg-rose-50/40'}>
                      <td className="py-2 px-3 font-mono text-[10px] text-slate-400">{row.row_number}</td>
                      <td className="py-2 px-3 font-bold text-slate-800">{row.name}</td>
                      <td className="py-2 px-3 text-slate-600">{row.phone}</td>
                      <td className="py-2 px-3">
                        {row.canonical_phone ? (
                          <span className="font-mono text-blue-600 font-bold">
                            {row.canonical_phone} &rarr; {row.registration_number}
                          </span>
                        ) : (
                          <span className="text-rose-600 font-bold text-[10px]">Invalid format</span>
                        )}
                      </td>
                      <td className="py-2 px-3 text-slate-600">
                        {row.center || 'N/A'} {row.seat_number ? `(${row.seat_number})` : ''}
                      </td>
                      <td className="py-2 px-3">
                        {row.is_duplicate_in_exam ? (
                          <span className="text-amber-600 font-bold text-[10px]">Already Registered</span>
                        ) : row.is_existing_student ? (
                          <span className="text-blue-600 font-bold text-[10px]">Profile Reused</span>
                        ) : row.is_valid ? (
                          <span className="text-emerald-600 font-bold text-[10px]">Ready to Enroll</span>
                        ) : (
                          <span className="text-rose-600 font-bold text-[10px]">Error</span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Action Bar */}
          <div className="flex items-center justify-between pt-2">
            <button
              onClick={() => setCurrentStep(2)}
              className="inline-flex items-center gap-2 px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50"
            >
              <ArrowLeft className="w-4 h-4" /> Re-upload File
            </button>

            <button
              onClick={handleConfirmImport}
              disabled={loading}
              className="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors cursor-pointer"
            >
              {loading ? 'Processing Import Batch...' : 'Confirm & Ingest Registrations'}
              <CheckCircle2 className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {/* STEP 4: Success Execution & Download Credentials */}
      {currentStep === 4 && importResult && (
        <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-xs text-center space-y-6">
          <div className="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-sm">
            <CheckCircle2 className="w-10 h-10" />
          </div>

          <div>
            <h2 className="text-2xl font-black text-slate-900 tracking-tight">Import Batch Completed Successfully!</h2>
            <p className="text-xs text-slate-500 mt-1">All verified student records have been safely enrolled into the examination.</p>
          </div>

          <div className="max-w-md mx-auto grid grid-cols-3 gap-3 text-left">
            <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span className="text-[10px] uppercase font-bold text-slate-400 block">Total Enrolled</span>
              <strong className="text-xl font-black text-slate-900">{importResult.imported_count}</strong>
            </div>

            <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span className="text-[10px] uppercase font-bold text-slate-400 block">Reused Profiles</span>
              <strong className="text-xl font-black text-blue-600">{importResult.reused_students_count}</strong>
            </div>

            <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
              <span className="text-[10px] uppercase font-bold text-slate-400 block">New Accounts</span>
              <strong className="text-xl font-black text-emerald-600">{importResult.new_accounts_count}</strong>
            </div>
          </div>

          {importResult.credential_export_token && (
            <div className="p-4 bg-amber-50 border border-amber-200 rounded-2xl max-w-lg mx-auto text-left">
              <div className="flex items-center gap-2 text-amber-900 text-xs font-bold mb-1">
                <KeyRound className="w-4 h-4 text-amber-600" /> One-Time Temporary Credentials Export
              </div>
              <p className="text-[11px] text-amber-800 mb-3">
                Download the newly provisioned student usernames (registration numbers) and temporary passwords. For security, this file is accessible only once.
              </p>
              <a
                href={`/api/admin/students/import/credentials/${importResult.credential_export_token}`}
                className="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-colors"
              >
                <Download className="w-4 h-4" /> Download Temporary Credentials (.CSV)
              </a>
            </div>
          )}

          <div className="pt-4">
            <button
              onClick={() => {
                setFile(null);
                setPreviewData(null);
                setImportResult(null);
                setCurrentStep(1);
              }}
              className="px-6 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-800"
            >
              Start Another Import
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
