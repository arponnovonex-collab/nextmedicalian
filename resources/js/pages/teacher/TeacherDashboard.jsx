import React, { useState, useEffect, useRef } from 'react';
import api from '../../api/axios';
import {
  PenTool,
  Search,
  CheckCircle2,
  AlertCircle,
  Clock,
  Layers,
  Sparkles,
  Lock,
  RotateCcw,
  UserCheck
} from 'lucide-react';

export default function TeacherDashboard() {
  const [assignedExams, setAssignedExams] = useState([]);
  const [selectedExamId, setSelectedExamId] = useState('');
  const [stats, setStats] = useState({ total_evaluated: 0, today_evaluated: 0 });

  // Evaluation search & student data
  const [regInput, setRegInput] = useState('');
  const [searching, setSearching] = useState(false);
  const [studentCard, setStudentCard] = useState(null);
  const [searchError, setSearchError] = useState('');

  // Marks inputs
  const [evalMode, setEvalMode] = useState('auto'); // 'auto' or 'direct'
  const [directMarks, setDirectMarks] = useState('');
  const [correctAnswers, setCorrectAnswers] = useState('');
  const [incorrectAnswers, setIncorrectAnswers] = useState('');
  const [unansweredQuestions, setUnansweredQuestions] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');

  // Refs for keyboard-friendly focus
  const searchInputRef = useRef(null);
  const directMarksInputRef = useRef(null);
  const correctInputRef = useRef(null);

  useEffect(() => {
    fetchAssignedExams();
  }, []);

  useEffect(() => {
    if (selectedExamId) {
      fetchTeacherStats();
      if (searchInputRef.current) {
        searchInputRef.current.focus();
      }
    }
  }, [selectedExamId]);

  const fetchAssignedExams = async () => {
    try {
      const res = await api.get('/teacher/assigned-exams');
      if (res.data.success && res.data.data.length > 0) {
        setAssignedExams(res.data.data);
        setSelectedExamId(res.data.data[0].id);
      }
    } catch (e) {}
  };

  const fetchTeacherStats = async () => {
    try {
      const res = await api.get(`/teacher/dashboard-stats?exam_id=${selectedExamId}`);
      if (res.data.success) {
        setStats({
          total_evaluated: res.data.data.total_evaluated,
          today_evaluated: res.data.data.today_evaluated,
        });
      }
    } catch (e) {}
  };

  const handleSearchStudent = async (e) => {
    if (e) e.preventDefault();
    if (!regInput.trim() || !selectedExamId) return;

    setSearching(true);
    setSearchError('');
    setStudentCard(null);
    setSuccessMessage('');

    try {
      const res = await api.get(`/teacher/search-student?exam_id=${selectedExamId}&reg_no=${encodeURIComponent(regInput.trim())}`);
      if (res.data.success) {
        setStudentCard(res.data.data);
        // Reset marks inputs
        setDirectMarks('');
        setCorrectAnswers('');
        setIncorrectAnswers('');
        setUnansweredQuestions('');

        // Auto focus appropriate input
        setTimeout(() => {
          if (!res.data.data.marks_already_assigned) {
            if (evalMode === 'auto' && correctInputRef.current) {
              correctInputRef.current.focus();
            } else if (directMarksInputRef.current) {
              directMarksInputRef.current.focus();
            }
          }
        }, 100);
      }
    } catch (err) {
      setSearchError(err.response?.data?.message || 'Candidate not found for this examination.');
    } finally {
      setSearching(false);
    }
  };

  // Calculated Preview for Mode B
  const calculatePreview = () => {
    if (!studentCard) return 0;
    const correct = parseInt(correctAnswers) || 0;
    const incorrect = parseInt(incorrectAnswers) || 0;
    const unanswered = parseInt(unansweredQuestions) || 0;

    const pos = correct * parseFloat(studentCard.marks_per_correct);
    const neg = incorrect * parseFloat(studentCard.negative_marks_per_incorrect);
    const un = unanswered * parseFloat(studentCard.marks_per_unanswered);

    return Math.max(0, parseFloat((pos - neg + un).toFixed(2)));
  };

  const handleSubmitMarks = async (e) => {
    e.preventDefault();
    if (!studentCard || studentCard.marks_already_assigned) return;

    setSubmitting(true);
    setSuccessMessage('');

    const payload = {
      registration_id: studentCard.registration_id,
      evaluation_mode: evalMode,
    };

    if (evalMode === 'direct') {
      payload.final_marks = parseFloat(directMarks);
    } else {
      payload.correct_answers = parseInt(correctAnswers) || 0;
      payload.incorrect_answers = parseInt(incorrectAnswers) || 0;
      payload.unanswered_questions = parseInt(unansweredQuestions) || 0;
    }

    try {
      const res = await api.post('/teacher/submit-marks', payload);
      if (res.data.success) {
        setSuccessMessage(`Marks (${res.data.data.final_marks}) successfully locked for ${studentCard.student_name} (${studentCard.registration_number})`);
        setStudentCard(null);
        setRegInput('');
        fetchTeacherStats();

        // Keyboard-friendly: Automatically refocus registration search input for rapid entry!
        setTimeout(() => {
          if (searchInputRef.current) searchInputRef.current.focus();
        }, 150);
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Error submitting marks.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Top Banner & Exam Selector */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Rapid Marks Evaluation Deck</h1>
          <p className="text-xs text-slate-500 mt-1">High-throughput physical answer script evaluation with automatic negative marking calculation.</p>
        </div>

        <div className="flex items-center gap-3">
          <div className="bg-white px-3 py-2 rounded-xl border border-slate-200 shadow-xs flex items-center gap-2">
            <span className="text-xs font-semibold text-slate-600">Assigned Exam:</span>
            <select
              value={selectedExamId}
              onChange={(e) => setSelectedExamId(e.target.value)}
              className="text-xs font-bold text-slate-800 bg-transparent focus:outline-hidden cursor-pointer"
            >
              {assignedExams.map(e => (
                <option key={e.id} value={e.id}>{e.code} - {e.title}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* KPI Counters */}
      <div className="grid grid-cols-2 gap-4">
        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
          <div>
            <span className="text-[10px] uppercase font-bold text-slate-400 block">Total Evaluated by You</span>
            <strong className="text-2xl font-black text-slate-900">{stats.total_evaluated}</strong>
            <span className="text-[10px] text-slate-400 block mt-0.5">Physical scripts graded</span>
          </div>
          <div className="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
            <PenTool className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
          <div>
            <span className="text-[10px] uppercase font-bold text-slate-400 block">Evaluated Today</span>
            <strong className="text-2xl font-black text-emerald-600">{stats.today_evaluated}</strong>
            <span className="text-[10px] text-emerald-600 font-semibold block mt-0.5">Session output</span>
          </div>
          <div className="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center">
            <CheckCircle2 className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Step 1: Candidate Search Box */}
      <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
        <h3 className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center gap-2">
          <Search className="w-4 h-4 text-blue-600" /> Step 1: Lookup Candidate by Registration Number
        </h3>

        <form onSubmit={handleSearchStudent} className="flex gap-2">
          <div className="relative flex-1">
            <input
              ref={searchInputRef}
              type="text"
              required
              placeholder="Type student Registration Number (e.g. 1712345678) and hit ENTER..."
              value={regInput}
              onChange={(e) => setRegInput(e.target.value)}
              className="w-full pl-4 pr-3 py-3 text-sm font-mono font-bold border-2 border-slate-200 rounded-xl focus:border-blue-600 focus:outline-hidden transition-all placeholder:font-sans placeholder:font-normal"
            />
          </div>
          <button
            type="submit"
            disabled={searching || !regInput.trim()}
            className="px-6 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-colors disabled:opacity-50 cursor-pointer"
          >
            {searching ? 'Finding...' : 'Search Script'}
          </button>
        </form>

        {searchError && (
          <div className="mt-3 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs font-bold text-rose-700 flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{searchError}</span>
          </div>
        )}

        {successMessage && (
          <div className="mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-bold text-emerald-700 flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{successMessage}</span>
          </div>
        )}
      </div>

      {/* Step 2: Student Card & Evaluation Form */}
      {studentCard && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-md overflow-hidden animate-in fade-in duration-150">
          {/* Candidate Verification Banner */}
          <div className="bg-slate-900 text-white p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <span className="text-[10px] font-bold uppercase tracking-widest text-blue-400 block mb-0.5">
                Answer Script Verification
              </span>
              <h2 className="text-xl font-black tracking-tight">{studentCard.student_name}</h2>
              <div className="text-xs text-slate-300 font-mono mt-0.5">
                Registration No: <strong className="text-blue-300">{studentCard.registration_number}</strong>
              </div>
            </div>

            <div className="text-left sm:text-right text-xs text-slate-300">
              <div className="font-semibold text-white">{studentCard.center_name}</div>
              <div>{studentCard.room_number} &bull; Seat: <strong className="text-white">{studentCard.seat_number}</strong></div>
              <div className="text-[10px] text-slate-400 mt-1">{studentCard.institution} ({studentCard.hsc_batch})</div>
            </div>
          </div>

          {/* STRICT DUPLICATE MARKS LOCKOUT BANNER */}
          {studentCard.marks_already_assigned ? (
            <div className="p-8 text-center space-y-3 bg-amber-50/50">
              <div className="w-14 h-14 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto">
                <Lock className="w-7 h-7" />
              </div>
              <div>
                <h3 className="text-lg font-black text-slate-900">Marks Already Assigned</h3>
                <p className="text-xs text-slate-600 max-w-md mx-auto mt-1">
                  Marks for this student have already been submitted and locked for this examination. Submission is disabled to ensure strict examination integrity.
                </p>
              </div>

              {studentCard.is_personally_evaluated && studentCard.personal_marks && (
                <div className="p-3 bg-white border border-slate-200 rounded-xl inline-block text-xs text-slate-700">
                  <span>Evaluated personally by you on: {new Date(studentCard.personal_marks.submitted_at).toLocaleDateString()}</span>
                  <div className="text-base font-black text-blue-600 mt-1">
                    Submitted Score: {studentCard.personal_marks.final_marks} Marks
                  </div>
                </div>
              )}

              <div className="pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setStudentCard(null);
                    setRegInput('');
                    if (searchInputRef.current) searchInputRef.current.focus();
                  }}
                  className="px-5 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase tracking-wider"
                >
                  Evaluate Next Candidate
                </button>
              </div>
            </div>
          ) : (
            /* Active Marks Entry Form */
            <form onSubmit={handleSubmitMarks} className="p-6 space-y-6">
              {/* Mode Selector Tabs */}
              <div className="flex items-center justify-between border-b border-slate-100 pb-4">
                <span className="text-xs font-bold text-slate-700 uppercase tracking-wider">Evaluation Method</span>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setEvalMode('auto')}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${evalMode === 'auto' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}
                  >
                    Automatic Calculation (Mode B)
                  </button>
                  <button
                    type="button"
                    onClick={() => setEvalMode('direct')}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all ${evalMode === 'direct' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}`}
                  >
                    Direct Marks Entry (Mode A)
                  </button>
                </div>
              </div>

              {/* Mode A: Direct Entry */}
              {evalMode === 'direct' ? (
                <div className="space-y-4 max-w-sm mx-auto text-center py-4">
                  <label className="block text-xs font-extrabold uppercase tracking-wider text-slate-700">
                    Obtained Final Marks (Max: {studentCard.total_marks}) *
                  </label>
                  <input
                    ref={directMarksInputRef}
                    type="number"
                    step="0.01"
                    min="0"
                    max={studentCard.total_marks}
                    required
                    placeholder="e.g. 74.50"
                    value={directMarks}
                    onChange={(e) => setDirectMarks(e.target.value)}
                    className="w-full py-4 text-center text-3xl font-black border-2 border-slate-200 rounded-2xl focus:border-blue-600 focus:outline-hidden"
                  />
                  <p className="text-[11px] text-slate-400">
                    Use when negative marks have already been computed manually on the physical answer script.
                  </p>
                </div>
              ) : (
                /* Mode B: Automatic Calculation */
                <div className="space-y-4">
                  <div className="p-3 bg-blue-50/60 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-center justify-between">
                    <span>Marking Formula: <strong>+{studentCard.marks_per_correct}</strong> per correct &bull; <strong>-{studentCard.negative_marks_per_incorrect}</strong> per wrong</span>
                    <span className="font-bold">Total Questions: {studentCard.total_questions}</span>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-emerald-700 uppercase tracking-wider mb-1">
                        Correct Answers *
                      </label>
                      <input
                        ref={correctInputRef}
                        type="number"
                        min="0"
                        max={studentCard.total_questions}
                        required
                        placeholder="e.g. 75"
                        value={correctAnswers}
                        onChange={(e) => setCorrectAnswers(e.target.value)}
                        className="w-full py-2.5 px-3 text-lg font-bold border-2 border-emerald-200 rounded-xl focus:border-emerald-600 focus:outline-hidden"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-rose-700 uppercase tracking-wider mb-1">
                        Incorrect Answers *
                      </label>
                      <input
                        type="number"
                        min="0"
                        max={studentCard.total_questions}
                        required
                        placeholder="e.g. 15"
                        value={incorrectAnswers}
                        onChange={(e) => setIncorrectAnswers(e.target.value)}
                        className="w-full py-2.5 px-3 text-lg font-bold border-2 border-rose-200 rounded-xl focus:border-rose-600 focus:outline-hidden"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                        Unanswered Questions *
                      </label>
                      <input
                        type="number"
                        min="0"
                        max={studentCard.total_questions}
                        required
                        placeholder="e.g. 10"
                        value={unansweredQuestions}
                        onChange={(e) => setUnansweredQuestions(e.target.value)}
                        className="w-full py-2.5 px-3 text-lg font-bold border-2 border-slate-200 rounded-xl focus:border-slate-600 focus:outline-hidden"
                      />
                    </div>
                  </div>

                  {/* Real-time Result Preview Banner */}
                  <div className="bg-slate-900 text-white p-4 rounded-xl flex items-center justify-between">
                    <div>
                      <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400 block">
                        Calculated Final Marks
                      </span>
                      <div className="text-2xl font-black text-emerald-400">
                        {calculatePreview()} <span className="text-sm font-normal text-slate-400">/ {studentCard.total_marks}</span>
                      </div>
                    </div>

                    <div className="text-right text-[11px] text-slate-300">
                      <div>Positive: +{(parseInt(correctAnswers) || 0) * parseFloat(studentCard.marks_per_correct)}</div>
                      <div className="text-rose-400">Penalty: -{(parseInt(incorrectAnswers) || 0) * parseFloat(studentCard.negative_marks_per_incorrect)}</div>
                    </div>
                  </div>
                </div>
              )}

              {/* Submit Button */}
              <div className="flex items-center justify-between pt-4 border-t border-slate-100">
                <button
                  type="button"
                  onClick={() => {
                    setStudentCard(null);
                    setRegInput('');
                    if (searchInputRef.current) searchInputRef.current.focus();
                  }}
                  className="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold hover:bg-slate-50"
                >
                  Cancel / Skip
                </button>

                <button
                  type="submit"
                  disabled={submitting}
                  className="inline-flex items-center gap-2 px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-md transition-colors disabled:opacity-50 cursor-pointer"
                >
                  {submitting ? 'Locking Marks...' : 'Confirm & Lock Marks (Enter)'}
                  <CheckCircle2 className="w-4 h-4" />
                </button>
              </div>
            </form>
          )}
        </div>
      )}
    </div>
  );
}
