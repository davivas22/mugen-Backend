import React, { useEffect, useState } from 'react';
import {
  StyleSheet,
  View,
  Text,
  ScrollView,
  Pressable,
  Platform,
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  TextInput,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withTiming,
  withDelay,
  Easing,
} from 'react-native-reanimated';
import {
  useFonts,
  BarlowCondensed_400Regular,
  BarlowCondensed_700Bold,
  BarlowCondensed_900Black,
} from '@expo-google-fonts/barlow-condensed';
import { useColors } from '../context/ThemeContext';
import { userApi, workoutApi, challengeApi } from '../../services/api';
import { storage } from '../../services/storage';

const ACCENT = '#FF0066';
const BOTTOM_NAV_HEIGHT = 60;
const HORIZONTAL_MARGIN = 16;

const DAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

const PRESET_WORKOUTS = [
  { title: 'CrossFit AMRAP', duration: '45', calories: '380', icon: 'dumbbell' },
  { title: 'Cardio Intenso', duration: '30', calories: '290', icon: 'run-fast' },
  { title: 'Fuerza Upper',   duration: '55', calories: '310', icon: 'arm-flex' },
  { title: 'Spinning',       duration: '40', calories: '350', icon: 'bike' },
];

type Workout = {
  id: string;
  title: string;
  duration: string;
  calories: number;
  icon: string;
  date: string;
};

type UserStats = {
  streak: number;
  total_calories: number;
  total_minutes: number;
  total_reps: number;
  total_workouts: number;
  total_hours: number;
  today_calories: number;
  today_minutes: number;
  points: number;
};

const cardShadow = Platform.select({
  ios: {
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 3,
  },
  android: { elevation: 2 },
  default: {},
});

type IoniconName = keyof typeof Ionicons.glyphMap;

function formatWorkoutDate(createdAt: string): string {
  const d = new Date(createdAt);
  const now = new Date();
  const diffMs = now.getTime() - d.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  if (diffDays === 0) return 'Hoy';
  if (diffDays === 1) return 'Ayer';
  const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
  return days[d.getDay()];
}

function QuickActionCard({
  icon,
  label,
  onPress,
  barlowFont,
  isDark,
}: {
  icon: IoniconName;
  label: string;
  onPress: () => void;
  barlowFont: (weight: '400' | '700' | '900') => object;
  isDark?: boolean;
}) {
  const scale = useSharedValue(1);
  const animatedStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
  }));

  return (
    <Pressable
      style={styles.quickActionPressable}
      onPress={onPress}
      onPressIn={() => { scale.value = withTiming(0.97, { duration: 80 }); }}
      onPressOut={() => { scale.value = withTiming(1, { duration: 120 }); }}
    >
      <Animated.View style={[styles.quickActionCard, cardShadow, animatedStyle, isDark && { backgroundColor: '#1A1A2E' }]}>
        <Ionicons name={icon} size={24} color={isDark ? '#FFFFFF' : '#0a0a0a'} />
        <Text style={[styles.quickActionLabel, barlowFont('700'), isDark && { color: '#FFFFFF' }]}>{label}</Text>
      </Animated.View>
    </Pressable>
  );
}

export default function DashboardScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { isDark, toggle } = useColors();

  const [fontsLoaded] = useFonts({
    BarlowCondensed_400Regular,
    BarlowCondensed_700Bold,
    BarlowCondensed_900Black,
  });

  const [workouts, setWorkouts] = useState<Workout[]>([]);
  const [userName, setUserName] = useState('Usuario');
  const [userStats, setUserStats] = useState<UserStats>({
    streak: 0, total_calories: 0, total_minutes: 0, total_reps: 0,
    today_calories: 0, today_minutes: 0,
    total_workouts: 0, total_hours: 0, points: 0,
  });
  const [challengesCount, setChallengesCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState('');

  const [showLogModal, setShowLogModal] = useState(false);
  const [workoutTitle, setWorkoutTitle] = useState('');
  const [duration, setDuration] = useState('');
  const [calories, setCalories] = useState('');
  const [selectedPresetIndex, setSelectedPresetIndex] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);

  const todayCalories = workouts
    .filter(w => w.date === 'Hoy')
    .reduce((sum, w) => sum + w.calories, 0);

  const todayMinutes = workouts
    .filter(w => w.date === 'Hoy')
    .reduce((sum, w) => sum + (parseInt(w.duration) || 0), 0);

  const loadData = async (tkn?: string) => {
    const t = tkn ?? token;
    if (!t) return;
    try {
      const [workoutsRes, statsRes, weeklyRes, challengesRes] = await Promise.all([
        workoutApi.list(t),
        userApi.stats(t),
        userApi.weekly(t),
        challengeApi.list(t),
      ]);
      const mapped: Workout[] = (workoutsRes.data.workouts ?? []).map((w: any) => ({
        id:       String(w.id),
        title:    w.title,
        duration: `${w.duration_minutes} min`,
        calories: w.calories,
        icon:     w.icon_name,
        date:     formatWorkoutDate(w.created_at),
      }));
      setWorkouts(mapped);
      setUserStats(statsRes.data);
      setChallengesCount((challengesRes.data.challenges ?? []).length);
    } catch (_) {}
  };

  useEffect(() => {
    (async () => {
      const [t, userRaw] = await Promise.all([storage.get('token'), storage.get('user')]);
      if (userRaw) {
        try { setUserName(JSON.parse(userRaw).name ?? 'Usuario'); } catch (_) {}
      }
      if (t) {
        setToken(t);
        await loadData(t);
      }
      setLoading(false);
    })();
  }, []);

  const handleSelectPreset = (index: number) => {
    setSelectedPresetIndex(index);
    const p = PRESET_WORKOUTS[index];
    setWorkoutTitle(p.title);
    setDuration(p.duration);
    setCalories(p.calories);
  };

  const handleAddWorkout = async () => {
    if (!workoutTitle.trim()) return;
    const parsedDuration = parseInt(duration) || 15;
    const parsedCalories = parseInt(calories) || Math.round(parsedDuration * 7.5);

    let icon = 'dumbbell';
    const tl = workoutTitle.toLowerCase();
    if (tl.includes('cardio') || tl.includes('run') || tl.includes('corr')) icon = 'run-fast';
    else if (tl.includes('bici') || tl.includes('spin')) icon = 'bike';
    else if (tl.includes('fuerza') || tl.includes('brazo') || tl.includes('arm')) icon = 'arm-flex';

    const optimistic: Workout = {
      id: `tmp-${Date.now()}`,
      title: workoutTitle,
      duration: `${parsedDuration} min`,
      calories: parsedCalories,
      icon,
      date: 'Hoy',
    };

    setWorkouts(prev => [optimistic, ...prev]);
    setWorkoutTitle('');
    setDuration('');
    setCalories('');
    setSelectedPresetIndex(null);
    setShowLogModal(false);

    if (token) {
      setSaving(true);
      try {
        await workoutApi.create({
          title: workoutTitle,
          duration_minutes: parsedDuration,
          calories: parsedCalories,
          icon_name: icon,
        }, token);
        await loadData();
      } catch (_) {} finally {
        setSaving(false);
      }
    }
  };

  /* ---- animations ---- */
  const headerOpacity = useSharedValue(0);
  const streakOpacity = useSharedValue(0);
  const streakTranslateY = useSharedValue(16);
  const calendarOpacity = useSharedValue(0);
  const statsOpacity = useSharedValue(0);
  const quickOpacity = useSharedValue(0);
  const historyOpacity = useSharedValue(0);

  useEffect(() => {
    const easeOut = Easing.out(Easing.ease);
    headerOpacity.value = withTiming(1, { duration: 500, easing: easeOut });
    streakOpacity.value = withDelay(300, withTiming(1, { duration: 500, easing: easeOut }));
    streakTranslateY.value = withDelay(300, withTiming(0, { duration: 500, easing: easeOut }));
    calendarOpacity.value = withDelay(350, withTiming(1, { duration: 500, easing: easeOut }));
    statsOpacity.value = withDelay(450, withTiming(1, { duration: 500, easing: easeOut }));
    quickOpacity.value = withDelay(550, withTiming(1, { duration: 500, easing: easeOut }));
    historyOpacity.value = withDelay(650, withTiming(1, { duration: 500, easing: easeOut }));
  }, []);

  const barlowFont = (weight: '400' | '700' | '900') => {
    if (!fontsLoaded) {
      return {
        fontFamily: Platform.select({
          ios: 'System',
          android: 'sans-serif',
          default: 'sans-serif',
        }),
        fontWeight: weight === '400' ? ('400' as const) : ('700' as const),
      };
    }
    switch (weight) {
      case '900': return { fontFamily: 'BarlowCondensed_900Black' };
      case '700': return { fontFamily: 'BarlowCondensed_700Bold' };
      default: return { fontFamily: 'BarlowCondensed_400Regular' };
    }
  };

  const headerStyle = useAnimatedStyle(() => ({ opacity: headerOpacity.value }));
  const streakStyle = useAnimatedStyle(() => ({
    opacity: streakOpacity.value,
    transform: [{ translateY: streakTranslateY.value }],
  }));
  const calendarStyle = useAnimatedStyle(() => ({ opacity: calendarOpacity.value }));
  const statsStyle = useAnimatedStyle(() => ({ opacity: statsOpacity.value }));
  const quickStyle = useAnimatedStyle(() => ({ opacity: quickOpacity.value }));
  const historyStyle = useAnimatedStyle(() => ({ opacity: historyOpacity.value }));

  const todayIndex = new Date().getDay() === 0 ? 6 : new Date().getDay() - 1;
  const today = new Date();
  const weekStart = today.getDate() - todayIndex;

  const scrollBottomPadding = BOTTOM_NAV_HEIGHT + Math.max(insets.bottom, 8) + 24;

  const HEADER_BG = isDark ? '#000000' : '#0a0a0a';
  const BODY_BG = isDark ? '#080810' : '#f5f5f7';
  const CARD_BG = isDark ? '#12121E' : '#ffffff';
  const TEXT_PRIMARY = isDark ? '#FFFFFF' : '#0a0a0a';
  const TEXT_SECONDARY = isDark ? 'rgba(255,255,255,0.55)' : '#888888';
  const BORDER_LIGHT = isDark ? 'rgba(255,255,255,0.07)' : '#f0f0f0';

  return (
    <View style={[styles.container, { backgroundColor: BODY_BG }]}>
      <StatusBar style={isDark ? 'light' : 'dark'} backgroundColor={HEADER_BG} />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingBottom: scrollBottomPadding }}
        style={[styles.scroll, { backgroundColor: BODY_BG }]}
      >
        {/* SECTION 1 — DARK HEADER */}
        <Animated.View style={[styles.header, headerStyle, { backgroundColor: HEADER_BG, paddingTop: insets.top + 8 }]}>
          <View style={styles.headerTopRow}>
            <Pressable
              style={styles.headerIconButton}
              onPress={() => console.log('Notificaciones')}
              accessibilityLabel="Notificaciones"
            >
              <Ionicons name="notifications-outline" size={22} color="#ffffff" />
            </Pressable>
            <View style={styles.headerRightGroup}>
              <Pressable style={[styles.themeToggle, { borderColor: 'rgba(255,255,255,0.2)' }]} onPress={toggle}>
                <Ionicons
                  name={isDark ? 'sunny' : 'moon'}
                  size={18}
                  color="#ffffff"
                />
              </Pressable>
              <Pressable
                style={styles.avatar}
                onPress={() => router.push('/perfil' as never)}
                accessibilityLabel="Perfil"
              >
                <Text style={styles.avatarText}>{userName.charAt(0).toUpperCase()}</Text>
              </Pressable>
            </View>
          </View>

          <Text style={styles.greeting}>Hola, {userName.split(' ')[0]}</Text>
          <Text style={[styles.xpValue, barlowFont('900')]}>
            {userStats.points > 0 ? `${userStats.points.toLocaleString('es-MX')} XP` : '0 XP'}
          </Text>
          <Pressable onPress={() => router.push('/ranking' as never)}>
            <Text style={styles.rankingLink}>Ranking esta semana →</Text>
          </Pressable>
        </Animated.View>

        {/* SECTION 2 — RACHA CARD (overlaps header) */}
        <Animated.View style={[styles.streakWrapper, streakStyle]}>
          <View style={[styles.streakCard, cardShadow, { backgroundColor: CARD_BG }]}>
            <View style={styles.streakLeft}>
              <Text style={[styles.streakLabel, { color: TEXT_SECONDARY }]}>RACHA ACTUAL</Text>
              {loading ? (
                <ActivityIndicator color={ACCENT} />
              ) : (
                <>
                  <Text style={[styles.streakValue, barlowFont('900'), { color: TEXT_PRIMARY }]}>
                    {userStats.streak} DÍAS
                  </Text>
                </>
              )}
              <Text style={[styles.streakSub, { color: TEXT_SECONDARY }]}>
                {userStats.total_workouts} entrenos totales
              </Text>
            </View>
            <View style={[styles.streakDivider, { backgroundColor: BORDER_LIGHT }]} />
            <View style={styles.streakRight}>
              <Text style={[styles.streakLabel, { color: TEXT_SECONDARY }]}>HOY</Text>
              <Text style={[styles.streakSideValue, barlowFont('700'), { color: TEXT_PRIMARY }]}>
                {todayCalories} kcal
              </Text>
              <Text style={[styles.streakSub, { color: TEXT_SECONDARY }]}>
                {todayMinutes} min
              </Text>
            </View>
          </View>
        </Animated.View>

        {/* LIGHT BODY */}
        <View style={[styles.body, { backgroundColor: BODY_BG }]}>
          {/* SECTION 3 — CALENDAR */}
          <Animated.View style={calendarStyle}>
            <Text style={[styles.sectionLabel, { color: TEXT_SECONDARY }]}>ESTA SEMANA</Text>
            <View style={[styles.calendarCard, cardShadow, { backgroundColor: CARD_BG }]}>
              <View style={styles.calendarRow}>
                {DAYS.map((day, i) => {
                  const isToday = i === todayIndex;
                  const dayNum = weekStart + i;
                  return (
                    <View key={day} style={styles.dayCol}>
                      <Text style={[styles.dayColName, { color: isToday ? ACCENT : TEXT_SECONDARY }]}>
                        {day}
                      </Text>
                      {isToday ? (
                        <View style={[styles.activeDayCircle, { backgroundColor: ACCENT }]}>
                          <Text style={styles.activeDayText}>{dayNum}</Text>
                        </View>
                      ) : (
                        <View style={styles.inactiveDayCircle}>
                          <Text style={[styles.inactiveDayText, { color: TEXT_PRIMARY }]}>{dayNum}</Text>
                        </View>
                      )}
                    </View>
                  );
                })}
              </View>
            </View>
          </Animated.View>

          {/* SECTION 4 — STATS */}
          <Animated.View style={statsStyle}>
            <View style={styles.statsRow}>
              <View style={[styles.statCard, cardShadow, { backgroundColor: CARD_BG }]}>
                <Ionicons name="barbell-outline" size={20} color={ACCENT} />
                <Text style={[styles.statValue, barlowFont('900'), { color: TEXT_PRIMARY }]}>
                  {loading ? '...' : userStats.total_workouts}
                </Text>
                <Text style={[styles.statLabel, { color: TEXT_SECONDARY }]}>WORKOUTS</Text>
              </View>
              <View style={[styles.statCard, cardShadow, { backgroundColor: CARD_BG }]}>
                <Ionicons name="flame-outline" size={20} color={ACCENT} />
                <Text style={[styles.statValue, barlowFont('900'), { color: TEXT_PRIMARY }]}>
                  {loading ? '...' : userStats.total_calories}
                </Text>
                <Text style={[styles.statLabel, { color: TEXT_SECONDARY }]}>CALORÍAS</Text>
              </View>
              <View style={[styles.statCard, cardShadow, { backgroundColor: CARD_BG }]}>
                <Ionicons name="trophy-outline" size={20} color={ACCENT} />
                <Text style={[styles.statValue, barlowFont('900'), { color: TEXT_PRIMARY }]}>
                  {loading ? '...' : challengesCount}
                </Text>
                <Text style={[styles.statLabel, { color: TEXT_SECONDARY }]}>CHALLENGES</Text>
              </View>
            </View>
            <View style={styles.statsRow}>
              <View style={[styles.statCard, cardShadow, { backgroundColor: CARD_BG }]}>
                <Ionicons name="time-outline" size={20} color={ACCENT} />
                <Text style={[styles.statValue, barlowFont('900'), { color: TEXT_PRIMARY }]}>
                  {loading ? '...' : userStats.total_minutes}
                </Text>
                <Text style={[styles.statLabel, { color: TEXT_SECONDARY }]}>MINUTOS</Text>
              </View>
              <View style={[styles.statCard, cardShadow, { backgroundColor: CARD_BG }]}>
                <Ionicons name="repeat-outline" size={20} color={ACCENT} />
                <Text style={[styles.statValue, barlowFont('900'), { color: TEXT_PRIMARY }]}>
                  {loading ? '...' : userStats.total_reps}
                </Text>
                <Text style={[styles.statLabel, { color: TEXT_SECONDARY }]}>REPETICIONES</Text>
              </View>
            </View>
          </Animated.View>

          {/* SECTION 5 — ACTIVIDAD RÁPIDA */}
          <Text style={[styles.sectionLabel, styles.sectionLabelSpaced, { color: TEXT_SECONDARY }]}>
            ACTIVIDAD RÁPIDA
          </Text>
          <Animated.View style={quickStyle}>
            <View style={styles.quickGrid}>
              <View style={styles.quickRow}>
                <QuickActionCard
                  icon="add"
                  label="NUEVA SALA"
                  onPress={() => router.push('/salas' as never)}
                  barlowFont={barlowFont}
                  isDark={isDark}
                />
                <QuickActionCard
                  icon="trophy-outline"
                  label="LEADERBOARD"
                  onPress={() => router.push('/ranking' as never)}
                  barlowFont={barlowFont}
                  isDark={isDark}
                />
              </View>
              <View style={styles.quickRow}>
                <QuickActionCard
                  icon="barbell-outline"
                  label="REGISTRAR"
                  onPress={() => setShowLogModal(true)}
                  barlowFont={barlowFont}
                  isDark={isDark}
                />
                <QuickActionCard
                  icon="qr-code-outline"
                  label="ESCANEAR QR"
                  onPress={() => router.push('/screens/ScanQRScreen' as never)}
                  barlowFont={barlowFont}
                  isDark={isDark}
                />
              </View>
            </View>
          </Animated.View>

          {/* SECTION 6 — HISTORIAL RECIENTE */}
          <Text style={[styles.sectionLabel, styles.sectionLabelSpaced, { color: TEXT_SECONDARY }]}>
            HISTORIAL RECIENTE
          </Text>
          <Animated.View style={historyStyle}>
            {loading ? (
              <ActivityIndicator color={ACCENT} style={{ marginTop: 12 }} />
            ) : workouts.length === 0 ? (
              <View style={[styles.historyCard, cardShadow, { backgroundColor: CARD_BG, padding: 32, alignItems: 'center', gap: 10 }]}>
                <Ionicons name="barbell-outline" size={32} color={TEXT_SECONDARY} />
                <Text style={[{ color: TEXT_SECONDARY, fontSize: 14, fontWeight: '600' }]}>
                  Aún no hay entrenamientos.
                </Text>
                <Pressable onPress={() => setShowLogModal(true)}>
                  <Text style={[{ color: ACCENT, fontSize: 13, fontWeight: '700' }]}>
                    Registra tu primera sesión →
                  </Text>
                </Pressable>
              </View>
            ) : (
              <View style={[styles.historyCard, cardShadow, { backgroundColor: CARD_BG }]}>
                {workouts.slice(0, 5).map((w, index) => {
                  const iconName: IoniconName =
                    w.icon === 'run-fast' ? 'fitness' :
                    w.icon === 'bike' ? 'bicycle' :
                    w.icon === 'arm-flex' ? 'fitness' : 'barbell';
                  return (
                    <View key={w.id}>
                      <View style={styles.historyRow}>
                        <Ionicons name={iconName} size={20} color={ACCENT} />
                        <View style={styles.historyCenter}>
                          <Text style={[styles.historyName, barlowFont('700'), { color: TEXT_PRIMARY }]}>{w.title}</Text>
                          <Text style={[styles.historyDate, { color: TEXT_SECONDARY }]}>
                            {w.duration} · {w.calories} kcal
                          </Text>
                        </View>
                        <View style={[styles.historyBadge, { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)' }]}>
                          <Text style={[styles.historyStat, barlowFont('700'), { color: TEXT_PRIMARY }]}>{w.date}</Text>
                        </View>
                      </View>
                      {index < Math.min(workouts.length, 5) - 1 ? (
                        <View style={[styles.historyDivider, { backgroundColor: BORDER_LIGHT }]} />
                      ) : null}
                    </View>
                  );
                })}
              </View>
            )}
          </Animated.View>
        </View>
      </ScrollView>

      {/* MODAL — REGISTRAR ENTRENO */}
      <Modal visible={showLogModal} transparent animationType="slide" onRequestClose={() => setShowLogModal(false)}>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.modalOverlay}>
          <View style={[styles.modalContainer, { backgroundColor: isDark ? '#12121E' : '#ffffff', borderColor: BORDER_LIGHT }]}>
            <View style={styles.modalHeader}>
              <Text style={[styles.modalTitle, barlowFont('700'), { color: TEXT_PRIMARY }]}>Registrar entrenamiento</Text>
              <Pressable onPress={() => setShowLogModal(false)}>
                <Ionicons name="close" size={24} color={TEXT_SECONDARY} />
              </Pressable>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={{ paddingBottom: 24 }}>
              <Text style={[styles.inputLabel, { color: TEXT_SECONDARY, marginTop: 8 }]}>Selecciona un preajuste</Text>
              <View style={styles.presetGrid}>
                {PRESET_WORKOUTS.map((preset, index) => {
                  const isSelected = selectedPresetIndex === index;
                  return (
                    <Pressable
                      key={preset.title}
                      onPress={() => handleSelectPreset(index)}
                      style={[styles.presetCard, {
                        backgroundColor: isSelected ? ACCENT + '15' : (isDark ? '#1A1A2E' : '#f5f5f7'),
                        borderColor: isSelected ? ACCENT : BORDER_LIGHT,
                      }]}
                    >
                      <MaterialCommunityIcons name={preset.icon as any} size={18} color={isSelected ? ACCENT : TEXT_SECONDARY} />
                      <Text style={[styles.presetText, { color: isSelected ? ACCENT : TEXT_PRIMARY }]} numberOfLines={1}>
                        {preset.title}
                      </Text>
                    </Pressable>
                  );
                })}
              </View>

              <View style={styles.formGroup}>
                <Text style={[styles.inputLabel, { color: TEXT_SECONDARY }]}>Tipo de entrenamiento</Text>
                <TextInput
                  value={workoutTitle}
                  onChangeText={txt => { setWorkoutTitle(txt); setSelectedPresetIndex(null); }}
                  placeholder="Ej. Spinning, Running, Yoga..."
                  placeholderTextColor={TEXT_SECONDARY}
                  style={[styles.inputField, { color: TEXT_PRIMARY, backgroundColor: isDark ? '#1A1A2E' : '#f5f5f7', borderColor: BORDER_LIGHT }]}
                />
              </View>

              <View style={styles.rowFields}>
                <View style={[styles.formGroup, { flex: 1, marginRight: 10 }]}>
                  <Text style={[styles.inputLabel, { color: TEXT_SECONDARY }]}>Duración (min)</Text>
                  <TextInput
                    value={duration}
                    onChangeText={setDuration}
                    keyboardType="number-pad"
                    placeholder="Ej. 30"
                    placeholderTextColor={TEXT_SECONDARY}
                    style={[styles.inputField, { color: TEXT_PRIMARY, backgroundColor: isDark ? '#1A1A2E' : '#f5f5f7', borderColor: BORDER_LIGHT }]}
                  />
                </View>
                <View style={[styles.formGroup, { flex: 1 }]}>
                  <Text style={[styles.inputLabel, { color: TEXT_SECONDARY }]}>Calorías (kcal)</Text>
                  <TextInput
                    value={calories}
                    onChangeText={setCalories}
                    keyboardType="number-pad"
                    placeholder="Opcional"
                    placeholderTextColor={TEXT_SECONDARY}
                    style={[styles.inputField, { color: TEXT_PRIMARY, backgroundColor: isDark ? '#1A1A2E' : '#f5f5f7', borderColor: BORDER_LIGHT }]}
                  />
                </View>
              </View>

              <Pressable
                onPress={handleAddWorkout}
                disabled={!workoutTitle.trim() || saving}
                style={[styles.submitBtn, { backgroundColor: workoutTitle.trim() ? ACCENT : 'rgba(128,128,128,0.3)' }]}
              >
                {saving
                  ? <ActivityIndicator color="#FFF" />
                  : <Text style={styles.submitBtnText}>ANCLAR A MI ACTIVIDAD</Text>
                }
              </Pressable>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  scroll: { flex: 1 },

  header: {
    paddingHorizontal: HORIZONTAL_MARGIN,
    paddingBottom: 40,
  },
  headerTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 24,
  },
  headerRightGroup: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  themeToggle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerIconButton: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    borderWidth: 1.5,
    borderColor: '#ffffff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '600',
  },
  greeting: {
    fontSize: 14,
    color: 'rgba(255, 255, 255, 0.55)',
    marginBottom: 8,
  },
  xpValue: {
    fontSize: 44,
    color: '#ffffff',
    letterSpacing: 0.5,
    lineHeight: 48,
    marginBottom: 8,
  },
  rankingLink: {
    fontSize: 13,
    color: ACCENT,
    fontWeight: '500',
  },

  streakWrapper: {
    marginTop: -28,
    marginHorizontal: HORIZONTAL_MARGIN,
    marginBottom: 24,
    zIndex: 2,
  },
  streakCard: {
    borderRadius: 12,
    padding: 20,
    flexDirection: 'row',
    alignItems: 'center',
  },
  streakLeft: { flex: 1, paddingRight: 16 },
  streakDivider: {
    width: 1,
    alignSelf: 'stretch',
    marginVertical: 4,
  },
  streakRight: { flex: 1, paddingLeft: 16, justifyContent: 'center' },
  streakLabel: {
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  streakValue: {
    fontSize: 28,
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  streakSub: { fontSize: 12 },
  streakSideValue: { fontSize: 18, marginTop: 2 },

  body: {
    paddingHorizontal: HORIZONTAL_MARGIN,
    paddingBottom: 8,
  },
  sectionLabel: {
    fontSize: 11,
    letterSpacing: 1.5,
    textTransform: 'uppercase',
    marginBottom: 12,
  },
  sectionLabelSpaced: { marginTop: 24 },

  calendarCard: {
    borderRadius: 12,
    padding: 16,
  },
  calendarRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  dayCol: { alignItems: 'center', gap: 6 },
  dayColName: { fontSize: 11, fontWeight: '700', letterSpacing: 0.5 },
  activeDayCircle: {
    width: 36, height: 36, borderRadius: 18,
    justifyContent: 'center', alignItems: 'center',
  },
  activeDayText: { fontSize: 13, fontWeight: '800', color: '#FFFFFF' },
  inactiveDayCircle: {
    width: 36, height: 36, borderRadius: 18,
    justifyContent: 'center', alignItems: 'center',
  },
  inactiveDayText: { fontSize: 13, fontWeight: '600' },

  statsRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
  statCard: {
    flex: 1,
    borderRadius: 12,
    padding: 14,
    alignItems: 'center',
    gap: 4,
  },
  statValue: { fontSize: 22, lineHeight: 26 },
  statLabel: { fontSize: 9, letterSpacing: 1, textTransform: 'uppercase' },

  quickGrid: { gap: 8 },
  quickRow: { flexDirection: 'row', gap: 8 },
  quickActionPressable: { flex: 1 },
  quickActionCard: {
    flex: 1,
    borderRadius: 10,
    paddingVertical: 18,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    minHeight: 96,
  },
  quickActionLabel: { fontSize: 9, letterSpacing: 0.8, textAlign: 'center' },

  historyCard: { borderRadius: 12, overflow: 'hidden' },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    height: 56,
    paddingHorizontal: 16,
    gap: 12,
  },
  historyCenter: { flex: 1 },
  historyName: { fontSize: 15, marginBottom: 2 },
  historyDate: { fontSize: 12 },
  historyBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  historyStat: { fontSize: 14 },
  historyDivider: { height: 1, marginLeft: 48 },

  /* MODAL */
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'flex-end',
  },
  modalContainer: {
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    borderWidth: 1,
    borderBottomWidth: 0,
    padding: 24,
    maxHeight: '85%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  modalTitle: { fontSize: 22, letterSpacing: 0.3 },
  presetGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginVertical: 10 },
  presetCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 12,
    borderWidth: 1,
    minWidth: '45%',
    flexGrow: 1,
  },
  presetText: { fontSize: 12, fontWeight: '600' },
  formGroup: { marginBottom: 16 },
  inputLabel: { fontSize: 11, fontWeight: '800', marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 },
  inputField: { height: 48, borderRadius: 12, borderWidth: 1, paddingHorizontal: 16, fontSize: 14 },
  rowFields: { flexDirection: 'row' },
  submitBtn: { height: 50, borderRadius: 12, justifyContent: 'center', alignItems: 'center', marginTop: 10 },
  submitBtnText: { color: '#FFF', fontSize: 12, fontWeight: '800', textTransform: 'uppercase', letterSpacing: 0.5 },
});
