import { useMemo, useState } from 'react';
import {
  ArrowRight,
  CheckCircle2,
  Cloud,
  HardDrive,
  Image,
  Lock,
  MessageCircle,
  ShieldCheck,
  Smartphone,
  Upload,
  Video
} from 'lucide-react';
import { mapAccountType, SynclyzUser } from './services/synclyzApi';

const transferItems = [
  { label: 'Images', icon: Image, progress: 72 },
  { label: 'Videos', icon: Video, progress: 48 },
  { label: 'Docs', icon: HardDrive, progress: 86 },
  { label: 'WhatsApp', icon: MessageCircle, progress: 30 }
];

const secureVault = [
  { name: 'Passport_scan.pdf', size: '2.4 MB', status: 'Encrypted' },
  { name: 'Family_vacation_4K.mp4', size: '2.1 GB', status: 'Encrypted' },
  { name: 'WhatsApp_backup_crypt14', size: '1.1 GB', status: 'Encrypted' }
];

const migrationOptions = ['Google Drive', 'iCloud', 'Synclyz'];

const initialUser: SynclyzUser = {
  username: 'alex.morgan',
  account_type: 'Free',
  level_id: '1',
  storage_used: '128 GB',
  storage_limit: '500 GB'
};

export default function App() {
  const [user, setUser] = useState<SynclyzUser>(initialUser);
  const [apiKey, setApiKey] = useState('');
  const [username, setUsername] = useState('');
  const [authStatus, setAuthStatus] = useState('Not connected');
  const [source, setSource] = useState('Google Drive');
  const [destination, setDestination] = useState('Synclyz');
  const [phone, setPhone] = useState('');
  const [otp, setOtp] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [verified, setVerified] = useState(false);

  const isPaid = user.account_type === 'Paid';

  const storagePercent = useMemo(() => {
    const used = Number(user.storage_used?.replace(/[^0-9.]/g, '') || 0);
    const limit = Number(user.storage_limit?.replace(/[^0-9.]/g, '') || 1);
    return Math.min(100, Math.round((used / limit) * 100));
  }, [user.storage_limit, user.storage_used]);

  const handleAuth = () => {
    if (!username || !apiKey) {
      setAuthStatus('Please enter your Synclyz username and API key.');
      return;
    }

    setAuthStatus('Authenticated via Synclyz API (placeholder).');
    setUser((prev) => ({
      ...prev,
      username,
      account_type: mapAccountType(prev.level_id),
      storage_used: '182 GB',
      storage_limit: '500 GB'
    }));
  };

  const handleSendOtp = () => {
    if (!phone) {
      setAuthStatus('Enter a phone number to receive the verification code.');
      return;
    }

    setOtpSent(true);
    setVerified(false);
  };

  const handleVerifyOtp = () => {
    if (!otp) {
      setAuthStatus('Enter the verification code sent to your phone.');
      return;
    }

    setVerified(true);
    setAuthStatus('Phone number verified for WhatsApp restore.');
  };

  const handleWhatsAppRestore = () => {
    if (!isPaid) {
      setAuthStatus('WhatsApp restore is a Premium feature. Please upgrade your Synclyz account.');
      return;
    }

    if (!verified) {
      setAuthStatus('Verify your phone number before restoring WhatsApp backups.');
      return;
    }

    setAuthStatus('WhatsApp restore initiated for the selected account.');
  };

  return (
    <div className="min-h-screen bg-midnight text-white">
      <header className="border-b border-white/10 bg-ocean/80">
        <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6">
          <div>
            <p className="text-sm uppercase tracking-[0.2em] text-sky-200">Synclyz</p>
            <h1 className="text-2xl font-semibold">Synclyz Cloud Bridge</h1>
            <p className="text-sm text-slate-300">Cloud migration and backup manager</p>
          </div>
          <div className="flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-4 py-2">
            <ShieldCheck className="h-5 w-5 text-emerald-300" />
            <div>
              <p className="text-xs text-slate-400">Account status</p>
              <p className="text-sm font-semibold text-white">{user.account_type} Tier</p>
            </div>
          </div>
        </div>
      </header>

      <main className="mx-auto w-full max-w-6xl space-y-8 px-6 py-10">
        <section className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          <div className="rounded-3xl border border-white/10 bg-gradient-to-br from-white/5 to-white/0 p-6">
            <h2 className="text-lg font-semibold">Connected Accounts</h2>
            <p className="text-sm text-slate-400">Manage cloud sources and destinations.</p>
            <div className="mt-6 grid gap-4 md:grid-cols-3">
              {['Google Drive', 'iCloud', 'Synclyz'].map((provider) => (
                <div
                  key={provider}
                  className="rounded-2xl border border-white/10 bg-white/5 p-4 shadow-lg"
                >
                  <div className="flex items-center justify-between">
                    <Cloud className="h-5 w-5 text-sky-300" />
                    <span className="rounded-full bg-emerald-400/10 px-3 py-1 text-xs text-emerald-200">
                      Connected
                    </span>
                  </div>
                  <p className="mt-4 text-base font-semibold">{provider}</p>
                  <p className="text-xs text-slate-400">Last sync: 8 mins ago</p>
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
            <h2 className="text-lg font-semibold">Synclyz API Login</h2>
            <p className="text-sm text-slate-400">
              Store your Yetishare API key to fetch storage limits and account status.
            </p>
            <div className="mt-5 space-y-3">
              <input
                value={username}
                onChange={(event) => setUsername(event.target.value)}
                placeholder="Synclyz username"
                className="w-full rounded-xl border border-white/10 bg-midnight/70 px-4 py-3 text-sm text-white placeholder:text-slate-500"
              />
              <input
                value={apiKey}
                onChange={(event) => setApiKey(event.target.value)}
                placeholder="API key"
                type="password"
                className="w-full rounded-xl border border-white/10 bg-midnight/70 px-4 py-3 text-sm text-white placeholder:text-slate-500"
              />
              <button
                onClick={handleAuth}
                className="flex w-full items-center justify-center gap-2 rounded-xl bg-sky-400 px-4 py-3 text-sm font-semibold text-midnight transition hover:bg-sky-300"
              >
                <Upload className="h-4 w-4" />
                Authenticate
              </button>
              <p className="text-xs text-slate-400">{authStatus}</p>
            </div>
            <div className="mt-6 rounded-2xl border border-white/10 bg-midnight/60 p-4">
              <p className="text-xs uppercase text-slate-500">Storage Usage</p>
              <div className="mt-2 flex items-center justify-between text-sm">
                <span>{user.storage_used}</span>
                <span className="text-slate-400">{user.storage_limit}</span>
              </div>
              <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-white/10">
                <div
                  className="h-full rounded-full bg-emerald-400"
                  style={{ width: `${storagePercent}%` }}
                />
              </div>
            </div>
          </div>
        </section>

        <section className="grid gap-6 lg:grid-cols-[1fr_1fr]">
          <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
            <h2 className="text-lg font-semibold">Migration Center</h2>
            <p className="text-sm text-slate-400">
              Select a source and destination to start streaming files into Synclyz.
            </p>
            <div className="mt-6 grid gap-4">
              <div className="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-midnight/70 p-4">
                <div>
                  <p className="text-xs uppercase text-slate-500">Source</p>
                  <select
                    value={source}
                    onChange={(event) => setSource(event.target.value)}
                    className="mt-2 w-full rounded-lg border border-white/10 bg-midnight px-3 py-2 text-sm"
                  >
                    {migrationOptions.map((option) => (
                      <option key={option} value={option}>
                        {option}
                      </option>
                    ))}
                  </select>
                </div>
                <ArrowRight className="h-5 w-5 text-slate-400" />
                <div>
                  <p className="text-xs uppercase text-slate-500">Destination</p>
                  <select
                    value={destination}
                    onChange={(event) => setDestination(event.target.value)}
                    className="mt-2 w-full rounded-lg border border-white/10 bg-midnight px-3 py-2 text-sm"
                  >
                    {migrationOptions.map((option) => (
                      <option key={option} value={option}>
                        {option}
                      </option>
                    ))}
                  </select>
                </div>
              </div>
              <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                  <p className="text-xs uppercase text-slate-500">Supported formats</p>
                  <p className="mt-2 text-sm text-slate-300">
                    Images (JPG, PNG, HEIC), Videos (MP4, MOV), Documents (PDF, DOC, XLS).
                  </p>
                </div>
                <div className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                  <p className="text-xs uppercase text-slate-500">Streaming mode</p>
                  <p className="mt-2 text-sm text-slate-300">
                    Multipart upload streaming enabled for large video transfers.
                  </p>
                </div>
              </div>
              <div className="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                <div className="flex items-center gap-3">
                  <CheckCircle2 className="h-5 w-5 text-emerald-300" />
                  <div>
                    <p className="text-sm font-semibold">Verification required</p>
                    <p className="text-xs text-emerald-100/80">
                      After upload, verify the hash before offering to delete the original file.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
            <h2 className="text-lg font-semibold">Active Transfers</h2>
            <p className="text-sm text-slate-400">Live progress for current migrations.</p>
            <div className="mt-6 space-y-4">
              {transferItems.map((item) => (
                <div key={item.label} className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <item.icon className="h-5 w-5 text-sky-300" />
                      <div>
                        <p className="text-sm font-semibold">{item.label}</p>
                        <p className="text-xs text-slate-500">Streaming to Synclyz</p>
                      </div>
                    </div>
                    <span className="text-sm text-slate-300">{item.progress}%</span>
                  </div>
                  <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-white/10">
                    <div className="h-full rounded-full bg-sky-400" style={{ width: `${item.progress}%` }} />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
          <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold">Secure Vault</h2>
                <p className="text-sm text-slate-400">Encrypted files stored inside Synclyz.</p>
              </div>
              <Lock className="h-5 w-5 text-emerald-300" />
            </div>
            <div className="mt-6 space-y-3">
              {secureVault.map((file) => (
                <div key={file.name} className="flex items-center justify-between rounded-2xl border border-white/10 bg-midnight/70 p-4">
                  <div>
                    <p className="text-sm font-semibold">{file.name}</p>
                    <p className="text-xs text-slate-500">{file.size}</p>
                  </div>
                  <span className="text-xs text-emerald-300">{file.status}</span>
                </div>
              ))}
            </div>
          </div>

          <div className="rounded-3xl border border-white/10 bg-white/5 p-6">
            <h2 className="text-lg font-semibold">WhatsApp Backups</h2>
            <p className="text-sm text-slate-400">
              Handle encrypted backups with phone verification and tiered restore access.
            </p>
            <div className="mt-5 space-y-4">
              <div className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                <p className="text-xs uppercase text-slate-500">Backup status</p>
                <div className="mt-2 flex items-center justify-between">
                  <span className="text-sm text-slate-200">Last backup: 2 days ago</span>
                  <span className="text-xs text-slate-400">1.1 GB</span>
                </div>
              </div>
              <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                  <p className="text-xs uppercase text-slate-500">Phone verification</p>
                  <div className="mt-3 space-y-3">
                    <input
                      value={phone}
                      onChange={(event) => setPhone(event.target.value)}
                      placeholder="+1 (555) 000-1234"
                      className="w-full rounded-lg border border-white/10 bg-midnight px-3 py-2 text-sm"
                    />
                    <button
                      onClick={handleSendOtp}
                      className="w-full rounded-lg border border-sky-400/60 bg-sky-400/10 px-3 py-2 text-xs font-semibold text-sky-200"
                    >
                      Send verification code
                    </button>
                  </div>
                </div>
                <div className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                  <p className="text-xs uppercase text-slate-500">Enter code</p>
                  <div className="mt-3 space-y-3">
                    <input
                      value={otp}
                      onChange={(event) => setOtp(event.target.value)}
                      placeholder="123456"
                      className="w-full rounded-lg border border-white/10 bg-midnight px-3 py-2 text-sm"
                    />
                    <button
                      onClick={handleVerifyOtp}
                      className="w-full rounded-lg border border-emerald-400/60 bg-emerald-400/10 px-3 py-2 text-xs font-semibold text-emerald-200"
                    >
                      Verify code
                    </button>
                  </div>
                </div>
              </div>
              <div className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <Smartphone className="h-5 w-5 text-sky-300" />
                    <div>
                      <p className="text-sm font-semibold">WhatsApp Restore</p>
                      <p className="text-xs text-slate-500">
                        {isPaid
                          ? 'Restore to any Gmail or iCloud account.'
                          : 'Premium feature for Paid Synclyz accounts.'}
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={handleWhatsAppRestore}
                    className={`rounded-lg px-4 py-2 text-xs font-semibold ${
                      isPaid
                        ? 'bg-emerald-400 text-midnight'
                        : 'cursor-not-allowed bg-white/10 text-slate-500'
                    }`}
                    disabled={!isPaid}
                  >
                    Restore
                  </button>
                </div>
                <p className="mt-3 text-xs text-slate-500">
                  Placeholder decryption flow ready. Connect drive.appdata scope in OAuth to access backups.
                </p>
              </div>
            </div>
          </div>
        </section>

        <section className="rounded-3xl border border-white/10 bg-white/5 p-6">
          <h2 className="text-lg font-semibold">Restore Center</h2>
          <p className="text-sm text-slate-400">
            Restore media and documents automatically. WhatsApp restore depends on subscription tier.
          </p>
          <div className="mt-6 grid gap-4 md:grid-cols-3">
            {[
              { label: 'Media Restore', status: 'Enabled' },
              { label: 'Docs Restore', status: 'Enabled' },
              { label: 'WhatsApp Restore', status: isPaid ? 'Enabled' : 'Locked' }
            ].map((item) => (
              <div key={item.label} className="rounded-2xl border border-white/10 bg-midnight/70 p-4">
                <div className="flex items-center justify-between">
                  <p className="text-sm font-semibold">{item.label}</p>
                  <span
                    className={`text-xs ${
                      item.status === 'Enabled' ? 'text-emerald-300' : 'text-amber-300'
                    }`}
                  >
                    {item.status}
                  </span>
                </div>
                <p className="mt-2 text-xs text-slate-500">
                  {item.status === 'Enabled'
                    ? 'Streaming restore ready.'
                    : 'Upgrade to unlock WhatsApp restores.'}
                </p>
              </div>
            ))}
          </div>
        </section>
      </main>
    </div>
  );
}
