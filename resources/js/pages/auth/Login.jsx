import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { Lock, User, AlertCircle, ArrowRight, ShieldCheck, Sparkles, GraduationCap, MapPin, Download } from 'lucide-react';

export default function Login() {
  const [activeTab, setActiveTab] = useState('student'); // 'student' or 'staff'
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const handleLogin = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const res = await login(username, password);
      if (res.success) {
        if (res.user.role === 'admin') navigate('/admin');
        else if (res.user.role === 'teacher') navigate('/teacher');
        else if (res.user.role === 'student') navigate('/student');
      } else {
        setError(res.message || 'Login failed.');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Invalid credentials or server error.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
      {/* Background Glows */}
      <div className="absolute top-0 -left-4 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
      <div className="absolute bottom-0 -right-4 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

      <div className="sm:mx-auto sm:w-full sm:max-w-md relative z-10 text-center mb-6">
        <div className="inline-flex items-center gap-2 px-3 py-1 bg-blue-950/60 border border-blue-500/30 rounded-full text-[11px] font-bold text-blue-400 uppercase tracking-widest mb-3">
          <Sparkles className="w-3.5 h-3.5" /> Powered by EdGenie
        </div>
        <h2 className="text-3xl font-extrabold text-white tracking-tight">NEXT MEDICALIAN</h2>
        <p className="mt-1 text-sm text-slate-400 font-medium">Centralized Offline Examination Management System</p>
      </div>

      <div className="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
        <div className="bg-white/95 backdrop-blur-md py-7 px-6 sm:px-9 shadow-2xl rounded-3xl border border-slate-100">
          {/* Tab Selector */}
          <div className="flex bg-slate-100 p-1 rounded-2xl mb-6">
            <button
              type="button"
              onClick={() => {
                setActiveTab('student');
                setError('');
              }}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'student'
                  ? 'bg-blue-600 text-white shadow-sm'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <GraduationCap className="w-4 h-4" />
              <span>Candidate Login</span>
            </button>
            <button
              type="button"
              onClick={() => {
                setActiveTab('staff');
                setError('');
              }}
              className={`flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                activeTab === 'staff'
                  ? 'bg-slate-900 text-white shadow-sm'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <ShieldCheck className="w-4 h-4" />
              <span>Admin / Teacher</span>
            </button>
          </div>

          {activeTab === 'student' ? (
            <div className="mb-5">
              <div className="flex items-center gap-2">
                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 className="text-base font-bold text-slate-900">Admit Card & Center Location</h3>
              </div>
              <p className="text-xs text-slate-500 mt-1">
                শিক্ষার্থীরা তাদের রেজিস্ট্রেশন নম্বর (বা মোবাইল নম্বর) এবং পাসওয়ার্ড দিয়ে প্রবেশ করে এডমিট কার্ড ডাউনলোড ও কেন্দ্র দেখতে পারবে।
              </p>
            </div>
          ) : (
            <div className="mb-5">
              <h3 className="text-base font-bold text-slate-900">Administrative Sign In</h3>
              <p className="text-xs text-slate-500 mt-1">Authorized access for exam controllers, teachers, and system administrators.</p>
            </div>
          )}

          {error && (
            <div className="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs font-semibold text-rose-700 flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleLogin} className="space-y-4">
            <div>
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                {activeTab === 'student' ? 'Registration No / Mobile Number' : 'Username / Staff ID'}
              </label>
              <div className="relative rounded-xl shadow-xs">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <User className="w-4 h-4" />
                </div>
                <input
                  type="text"
                  required
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder={
                    activeTab === 'student'
                      ? 'e.g. 01876121187 or 1876121187'
                      : 'e.g. admin or teacher1'
                  }
                  className="block w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                Password
              </label>
              <div className="relative rounded-xl shadow-xs">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                  <Lock className="w-4 h-4" />
                </div>
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder={activeTab === 'student' ? 'Enter 4-digit SMS password (or mobile number)' : '••••••••'}
                  className="block w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-hidden focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all"
                />
              </div>
              {activeTab === 'student' && (
                <p className="mt-1.5 text-[11px] text-slate-500">
                  পাসওয়ার্ড: আপনার মোবাইলে এসএমএস-এ পাঠানো ৪ ডিজিটের পাসওয়ার্ড (অথবা আপনার মোবাইল নম্বর)।
                </p>
              )}
            </div>

            <button
              type="submit"
              disabled={loading}
              className={`w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl shadow-md text-xs font-bold uppercase tracking-wider text-white transition-all disabled:opacity-50 cursor-pointer ${
                activeTab === 'student'
                  ? 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-600'
                  : 'bg-slate-900 hover:bg-slate-800 focus:ring-slate-900'
              }`}
            >
              {loading ? (
                'Authenticating...'
              ) : activeTab === 'student' ? (
                <>
                  <Download className="w-4 h-4" />
                  <span>View Center & Download Admit</span>
                </>
              ) : (
                <>
                  <span>Sign In to Portal</span>
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>

          {activeTab === 'student' && (
            <div className="mt-5 p-3 bg-blue-50/80 border border-blue-100 rounded-xl flex items-center gap-2 text-xs text-blue-800">
              <MapPin className="w-4 h-4 shrink-0 text-blue-600" />
              <span>লগইন করলেই আপনার নির্ধারিত পরীক্ষার কেন্দ্র, আসন ও Google Maps লোকেশন দেখতে পাবেন।</span>
            </div>
          )}
        </div>

        <div className="mt-6 text-center text-xs text-slate-500 flex items-center justify-center gap-1.5">
          <ShieldCheck className="w-4 h-4 text-emerald-500" />
          <span>Secured with Encrypted Authentication &bull; Asia/Dhaka</span>
        </div>
      </div>
    </div>
  );
}
