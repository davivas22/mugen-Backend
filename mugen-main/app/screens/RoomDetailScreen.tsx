import React, { useEffect, useState, useCallback } from 'react';
import {
  StyleSheet,
  View,
  Text,
  ScrollView,
  Pressable,
  TextInput,
  Platform,
  ActivityIndicator,
  Alert,
  Modal,
} from 'react-native';
import * as Clipboard from 'expo-clipboard';
import { Image } from 'expo-image';
import { LinearGradient } from 'expo-linear-gradient';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useRouter, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { Ionicons } from '@expo/vector-icons';
import Animated, {
  useSharedValue,
  useAnimatedStyle,
  withTiming,
  withSpring,
  withDelay,
  Easing,
} from 'react-native-reanimated';
import {
  useFonts,
  BarlowCondensed_400Regular,
  BarlowCondensed_700Bold,
  BarlowCondensed_900Black,
} from '@expo-google-fonts/barlow-condensed';
import { workoutApi, challengeApi } from '../../services/api';
import { storage } from '../../services/storage';

const ACCENT = '#FF0066';
const BG_DARK = '#0a0a0a';
const BODY_BG = '#f5f5f7';
const CARD_BG = '#111113';
const CARD_BORDER = '#161618';
const TEXT_LIGHT = '#f0f0f0';
const TEXT_SECONDARY = '#888888';
const TEXT_MUTED = '#aaaaaa';
const HORIZONTAL_MARGIN = 16;

const HERO_IMAGE = 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?w=900&q=85&auto=format&fit=crop';

export default function RoomDetailScreen() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();

  const [fontsLoaded] = useFonts({
    BarlowCondensed_400Regular,
    BarlowCondensed_700Bold,
    BarlowCondensed_900Black,
  });

  const [token, setToken] = useState('');
  const [saving, setSaving] = useState(false);
  const [reps, setReps] = useState('');
  const [weight, setWeight] = useState('');
  const [steps, setSteps] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [recentWorkouts, setRecentWorkouts] = useState<{ id: string; title: string; duration: string; calories: number; date: string }[]>([]);
  const [selectedPeriod, setSelectedPeriod] = useState<'semana' | 'mes' | 'año'>('semana');
  const [leaderboard, setLeaderboard] = useState<{
    id: number;
    username: string;
    avatar_url: string | null;
    rank: number;
    points: number;
    sessions: number;
    total_reps: number;
  }[]>([]);
  const [loadingLeaderboard, setLoadingLeaderboard] = useState(false);
  const [challengeName, setChallengeName] = useState('');
  const [inviteCode, setInviteCode] = useState('');
  const [copied, setCopied] = useState(false);
  const [currentUser, setCurrentUser] = useState<any>(null);
  const [challengeCreatorId, setChallengeCreatorId] = useState<number | null>(null);
  const [showMenu, setShowMenu] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editName, setEditName] = useState('');
  const [savingEdit, setSavingEdit] = useState(false);

  const buttonScale = useSharedValue(1);
  const contentOpacity = useSharedValue(0);
  const contentTranslateY = useSharedValue(20);
  const timerOpacity = useSharedValue(0);
  const leaderboardOpacity = useSharedValue(0);
  const progresoOpacity = useSharedValue(0);

  useEffect(() => {
    const easeOut = Easing.out(Easing.ease);
    contentOpacity.value = withTiming(1, { duration: 400, easing: easeOut });
    contentTranslateY.value = withTiming(0, { duration: 400, easing: easeOut });
    timerOpacity.value = withDelay(150, withTiming(1, { duration: 350, easing: easeOut }));
    leaderboardOpacity.value = withDelay(300, withTiming(1, { duration: 350, easing: easeOut }));
    progresoOpacity.value = withDelay(450, withTiming(1, { duration: 350, easing: easeOut }));

    (async () => {
      const t = await storage.get('token');
      const u = await storage.get('user');
      if (u) setCurrentUser(typeof u === 'string' ? JSON.parse(u) : u);
      if (t) {
        setToken(t);
        loadRecent(t);
        fetchLeaderboard(t);
      }
    })();
  }, []);

  const loadRecent = async (tkn?: string) => {
    const t = tkn ?? token;
    if (!t) return;
    try {
      const res = await workoutApi.list(t);
      const mapped = (res.data.workouts ?? []).slice(0, 5).map((w: any) => {
        const d = new Date(w.created_at);
        const now = new Date();
        const diffMs = now.getTime() - d.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        let date = '';
        if (diffDays === 0) date = 'Hoy';
        else if (diffDays === 1) date = 'Ayer';
        else date = d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
        return {
          id: String(w.id),
          title: w.title,
          duration: `${w.duration_minutes} min`,
          calories: w.calories,
          date,
        };
      });
      setRecentWorkouts(mapped);
    } catch (e) {
      console.error('[RoomDetail] Error al cargar actividad reciente', e);
    }
  };

  const fetchLeaderboard = async (tkn?: string, period?: 'semana' | 'mes' | 'año') => {
    const t = tkn ?? token;
    const p = period ?? selectedPeriod;
    if (!t || !id) return;
    setLoadingLeaderboard(true);
    try {
      const res = await challengeApi.leaderboard(Number(id), p, t);
      const data = res.data;
      setChallengeName(data.challenge?.name ?? '');
      setInviteCode(data.challenge?.invite_code ?? '');
      setChallengeCreatorId(data.challenge?.user_id ?? null);
      setLeaderboard(data.participants ?? []);
    } catch {
      setChallengeName('');
      setLeaderboard([]);
    }
    setLoadingLeaderboard(false);
  };

  const handlePeriodChange = (period: 'semana' | 'mes' | 'año') => {
    setSelectedPeriod(period);
    fetchLeaderboard(token, period);
  };

  useEffect(() => {
    if (submitted) {
      const t = setTimeout(() => {
        setReps('');
        setWeight('');
        setSteps('');
        setSubmitted(false);
      }, 1500);
      return () => clearTimeout(t);
    }
  }, [submitted]);

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

  const contentAnimatedStyle = useAnimatedStyle(() => ({
    opacity: contentOpacity.value,
    transform: [{ translateY: contentTranslateY.value }],
  }));

  const timerAnimatedStyle = useAnimatedStyle(() => ({
    opacity: timerOpacity.value,
  }));

  const leaderboardAnimatedStyle = useAnimatedStyle(() => ({
    opacity: leaderboardOpacity.value,
  }));

  const progresoAnimatedStyle = useAnimatedStyle(() => ({
    opacity: progresoOpacity.value,
  }));

  const buttonStyle = useAnimatedStyle(() => ({
    transform: [{ scale: buttonScale.value }],
  }));

  const hasAnyInput = reps.trim() || weight.trim() || steps.trim();

  const handleRegister = useCallback(async () => {
    if (!hasAnyInput) return;

    setSaving(true);
    if (token) {
      try {
        await workoutApi.create({
          title: 'Progreso en sala',
          duration_minutes: 0,
          calories: Number(weight) || 0,
          icon_name: 'barbell',
          challenge_id: Number(id),
          reps: Number(reps) || 0,
        }, token);
      } catch (e) {
        console.error('[RoomDetail] Error al registrar progreso', e);
      }
    }
    setSaving(false);
    setSubmitted(true);
    loadRecent();
    fetchLeaderboard();
  }, [hasAnyInput, token, id, selectedPeriod]);

  const handleCopyCode = useCallback(async () => {
    if (!inviteCode) return;
    await Clipboard.setStringAsync(inviteCode);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, [inviteCode]);

  const isCreator = currentUser && challengeCreatorId && Number(currentUser.id) === challengeCreatorId;

  const handleEdit = () => {
    setShowMenu(false);
    setEditName(challengeName);
    setShowEditModal(true);
  };

  const handleSaveEdit = async () => {
    if (!editName.trim() || !token) return;
    setSavingEdit(true);
    try {
      const res = await challengeApi.update(id, { name: editName.trim() }, token);
      setChallengeName(res.data.challenge?.name ?? editName.trim());
      setShowEditModal(false);
    } catch (e: any) {
      Alert.alert('Error', e?.response?.data?.message ?? 'No se pudo actualizar la sala.');
    }
    setSavingEdit(false);
  };

  const handleDelete = () => {
    setShowMenu(false);
    Alert.alert(
      'Eliminar sala',
      '¿Estás seguro? Esta acción no se puede deshacer.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Eliminar',
          style: 'destructive',
          onPress: async () => {
            if (!token) return;
            try {
              await challengeApi.delete(id, token);
              router.back();
            } catch (e: any) {
              Alert.alert('Error', e?.response?.data?.message ?? 'No se pudo eliminar la sala.');
            }
          },
        },
      ]
    );
  };

  const scrollBottomPadding = 24 + Math.max(insets.bottom, 8);

  return (
    <SafeAreaView style={styles.safeArea} edges={['bottom']}>
      <StatusBar style="light" />

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{ paddingBottom: scrollBottomPadding }}
        style={styles.scroll}
      >
        {/* HERO */}
        <View style={styles.heroContainer}>
          <Image source={{ uri: HERO_IMAGE }} style={styles.heroImage} contentFit="cover" />
          <LinearGradient
            colors={['rgba(10, 10, 10, 0.4)', 'rgba(10, 10, 10, 0.95)']}
            locations={[0, 0.55]}
            style={styles.heroGradient}
          >
            <View style={[styles.heroTopRow, { paddingTop: 12 }]}>
              <Pressable onPress={() => router.back()} hitSlop={8} style={styles.heroBackBtn}>
                <Ionicons name="arrow-back" size={22} color="#ffffff" />
              </Pressable>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                <Pressable onPress={handleCopyCode} style={styles.heroCodePill}>
                  <Ionicons name="copy-outline" size={12} color={ACCENT} />
                  <Text style={styles.heroCodePillText}>
                    {copied ? '¡COPIADO!' : inviteCode || 'CARGANDO...'}
                  </Text>
                </Pressable>
                {isCreator && (
                  <Pressable onPress={() => setShowMenu(true)} style={styles.heroMenuBtn}>
                    <Ionicons name="ellipsis-vertical" size={18} color="#ffffff" />
                  </Pressable>
                )}
              </View>
            </View>

            <View style={styles.heroBottomContent}>
              <View style={styles.heroCodeBadge}>
                <Text style={styles.heroCodeText}>SALA #{id}</Text>
              </View>
              <Text style={[styles.heroTitle, barlowFont('900')]}>{challengeName || `SALA #${id}`}</Text>
            </View>
          </LinearGradient>
        </View>

        {/* CONTENT */}
        <Animated.View style={[styles.contentArea, contentAnimatedStyle]}>
          {/* TIMER — placeholder */}
          <Animated.View style={timerAnimatedStyle}>
            <View style={styles.sectionCard}>
              <Text style={[styles.sectionCardLabel, { color: TEXT_SECONDARY }]}>RETO DE LA SALA</Text>
              <Text style={[styles.sectionCardTitle, barlowFont('700'), { color: TEXT_LIGHT }]}>
                Próximamente
              </Text>
              <View style={styles.placeholderRow}>
                <Ionicons name="timer-outline" size={18} color={TEXT_MUTED} />
                <Text style={styles.placeholderText}>El temporizador del reto aparecerá aquí</Text>
              </View>
            </View>
          </Animated.View>

          {/* LEADERBOARD — functional */}
          <Animated.View style={[styles.leaderboardSection, leaderboardAnimatedStyle]}>
            <Text style={[styles.sectionTitle, barlowFont('700')]}>LEADERBOARD</Text>
            <Text style={styles.sectionSubtitle}>
              {challengeName
                ? `Clasificación de ${challengeName}`
                : 'Clasificación basada en el progreso registrado'}
            </Text>

            {/* Podium */}
            <View style={styles.sectionCard}>
              {leaderboard.length === 0 && !loadingLeaderboard ? (
                <Text style={styles.placeholderText}>Aún no hay participantes con progreso registrado</Text>
              ) : (
                <>
                  <View style={styles.podiumRow}>
                    {[1, 0, 2].map((posIdx) => {
                      const p = leaderboard[posIdx];
                      const actualRank = posIdx + 1;
                      const isFirst = posIdx === 0;
                      if (!p) return null;
                      return (
                        <View
                          key={p.id}
                          style={[
                            styles.podiumItem,
                            isFirst && styles.podiumItemCenter,
                          ]}
                        >
                          <View style={[styles.podiumCircle, isFirst && styles.podiumCircleGold]}>
                            <Ionicons
                              name={isFirst ? 'trophy' : 'ellipse-outline'}
                              size={isFirst ? 20 : 16}
                              color={isFirst ? ACCENT : TEXT_MUTED}
                            />
                          </View>
                          <Text numberOfLines={1} style={[styles.podiumUsername, barlowFont('700')]}>
                            {p.username}
                          </Text>
                          <Text style={styles.podiumPoints}>{p.points} pts</Text>
                          <View
                            style={[
                              styles.podiumBar,
                              {
                                height: isFirst ? 64 : posIdx === 2 ? 44 : 32,
                                backgroundColor: isFirst ? ACCENT + '30' : CARD_BORDER,
                              },
                            ]}
                          />
                        </View>
                      );
                    })}
                  </View>
                  {leaderboard.length > 3 && (
                    <View style={styles.podiumMoreRow}>
                      <Ionicons name="people" size={14} color={TEXT_MUTED} />
                      <Text style={styles.podiumMoreText}>
                        +{leaderboard.length - 3} participantes más abajo
                      </Text>
                    </View>
                  )}
                </>
              )}
            </View>

            {/* Period filters */}
            <View style={styles.filterRow}>
              {(['semana', 'mes', 'año'] as const).map((p) => (
                <Pressable
                  key={p}
                  style={[
                    styles.filterBtn,
                    selectedPeriod === p
                      ? { backgroundColor: ACCENT }
                      : { backgroundColor: CARD_BORDER },
                  ]}
                  onPress={() => handlePeriodChange(p)}
                >
                  <Text
                    style={[
                      styles.filterBtnText,
                      selectedPeriod === p
                        ? { color: '#ffffff' }
                        : { color: TEXT_MUTED },
                    ]}
                  >
                    {p === 'semana' ? 'SEMANA' : p === 'mes' ? 'MES' : 'AÑO'}
                  </Text>
                </Pressable>
              ))}
            </View>

            {/* Participants list */}
            {loadingLeaderboard ? (
              <ActivityIndicator color={ACCENT} style={{ marginVertical: 20 }} />
            ) : leaderboard.length === 0 ? (
              <View style={styles.leaderboardEmpty}>
                <Ionicons name="bar-chart-outline" size={24} color={TEXT_MUTED} />
                <Text style={styles.leaderboardEmptyText}>
                  No hay datos de progreso para este período
                </Text>
              </View>
            ) : (
              leaderboard.map((p) => (
                <View key={p.id} style={styles.participantCard}>
                  <View style={styles.participantLeft}>
                    <Text style={styles.participantRank}>#{p.rank}</Text>
                    <View style={styles.participantAvatar}>
                      <Ionicons name="person" size={16} color={TEXT_MUTED} />
                    </View>
                    <Text style={[styles.participantName, barlowFont('700')]} numberOfLines={1}>
                      {p.username}
                    </Text>
                  </View>
                  <View style={styles.participantStats}>
                    <View style={{ alignItems: 'center', minWidth: 44 }}>
                      <Text style={styles.statValue}>{p.points}</Text>
                      <Text style={styles.statLabel}>PTS</Text>
                    </View>
                    <View style={styles.participantStatDivider} />
                    <View style={{ alignItems: 'center', minWidth: 36 }}>
                      <Text style={styles.statValue}>{p.sessions}</Text>
                      <Text style={styles.statLabel}>SES</Text>
                    </View>
                    <View style={styles.participantStatDivider} />
                    <View style={{ alignItems: 'center', minWidth: 44 }}>
                      <Text style={styles.statValue}>{p.total_reps}</Text>
                      <Text style={styles.statLabel}>REPS</Text>
                    </View>
                  </View>
                </View>
              ))
            )}
          </Animated.View>

          {/* PROGRESO FORM — functional */}
          <Animated.View style={progresoAnimatedStyle}>
            <View style={styles.progresoCard}>
              <Text style={[styles.progresoTitle, barlowFont('700')]}>MI PROGRESO</Text>

              <View style={styles.inputRow}>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>REPS</Text>
                  <TextInput
                    style={styles.input}
                    value={reps}
                    onChangeText={setReps}
                    placeholder="0"
                    placeholderTextColor={TEXT_MUTED}
                    keyboardType="number-pad"
                  />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>PESO (KG)</Text>
                  <TextInput
                    style={styles.input}
                    value={weight}
                    onChangeText={setWeight}
                    placeholder="0"
                    placeholderTextColor={TEXT_MUTED}
                    keyboardType="decimal-pad"
                  />
                </View>
                <View style={styles.inputGroup}>
                  <Text style={styles.inputLabel}>PASOS</Text>
                  <TextInput
                    style={styles.input}
                    value={steps}
                    onChangeText={setSteps}
                    placeholder="0"
                    placeholderTextColor={TEXT_MUTED}
                    keyboardType="number-pad"
                  />
                </View>
              </View>

              <Pressable
                onPress={handleRegister}
                onPressIn={() => { buttonScale.value = withSpring(0.95); }}
                onPressOut={() => { buttonScale.value = withSpring(1); }}
                disabled={!hasAnyInput || saving}
              >
                <Animated.View
                  style={[
                    styles.registerBtn,
                    (!hasAnyInput || saving) && styles.registerBtnDisabled,
                    buttonStyle,
                  ]}
                >
                  {saving ? (
                    <ActivityIndicator color="#ffffff" />
                  ) : (
                    <Text style={styles.registerBtnText}>
                      {submitted ? 'REGISTRADO ✓' : 'REGISTRAR'}
                    </Text>
                  )}
                </Animated.View>
              </Pressable>
            </View>

            {/* RECENT ACTIVITY */}
            <View style={styles.recentCard}>
              <Text style={[styles.recentTitle, barlowFont('700')]}>ACTIVIDAD RECIENTE</Text>
              {recentWorkouts.length === 0 ? (
                <View style={styles.recentEmpty}>
                  <Ionicons name="barbell-outline" size={20} color={TEXT_MUTED} />
                  <Text style={styles.recentEmptyText}>Aún no hay actividad registrada</Text>
                </View>
              ) : (
                recentWorkouts.map((w) => (
                  <View key={w.id} style={styles.recentRow}>
                    <View style={styles.recentRowLeft}>
                      <Ionicons name="barbell" size={16} color={ACCENT} />
                      <View style={styles.recentRowCenter}>
                        <Text style={styles.recentRowTitle} numberOfLines={1}>{w.title}</Text>
                        <Text style={styles.recentRowMeta}>{w.duration} · {w.calories} kcal</Text>
                      </View>
                    </View>
                    <Text style={styles.recentRowDate}>{w.date}</Text>
                  </View>
                ))
              )}
            </View>
          </Animated.View>
        </Animated.View>
      </ScrollView>

      {/* Options menu overlay */}
      {showMenu && (
        <Pressable style={styles.menuOverlay} onPress={() => setShowMenu(false)}>
          <View style={styles.menuSheet}>
            <Pressable style={styles.menuOption} onPress={handleEdit}>
              <Ionicons name="pencil-outline" size={18} color={TEXT_LIGHT} />
              <Text style={styles.menuOptionText}>Editar sala</Text>
            </Pressable>
            <View style={styles.menuDivider} />
            <Pressable style={styles.menuOption} onPress={handleDelete}>
              <Ionicons name="trash-outline" size={18} color={ACCENT} />
              <Text style={[styles.menuOptionText, { color: ACCENT }]}>Eliminar sala</Text>
            </Pressable>
          </View>
        </Pressable>
      )}

      {/* Edit name modal */}
      <Modal visible={showEditModal} transparent animationType="fade">
        <Pressable style={styles.modalOverlay} onPress={() => setShowEditModal(false)}>
          <Pressable style={styles.editModalContent} onPress={() => {}}>
            <Text style={[styles.editModalTitle, barlowFont('700')]}>EDITAR SALA</Text>
            <TextInput
              style={styles.editModalInput}
              value={editName}
              onChangeText={setEditName}
              placeholder="Nombre de la sala"
              placeholderTextColor={TEXT_MUTED}
              autoFocus
            />
            <View style={styles.editModalRow}>
              <Pressable
                style={[styles.editModalBtn, { backgroundColor: CARD_BORDER }]}
                onPress={() => setShowEditModal(false)}
              >
                <Text style={styles.editModalBtnText}>CANCELAR</Text>
              </Pressable>
              <Pressable
                style={[styles.editModalBtn, { backgroundColor: ACCENT }, !editName.trim() && { opacity: 0.4 }]}
                onPress={handleSaveEdit}
                disabled={!editName.trim() || savingEdit}
              >
                {savingEdit ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text style={styles.editModalBtnText}>GUARDAR</Text>
                )}
              </Pressable>
            </View>
          </Pressable>
        </Pressable>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: BODY_BG,
  },
  scroll: {
    flex: 1,
    backgroundColor: BG_DARK,
  },

  heroContainer: {
    height: 300,
    width: '100%',
    position: 'relative',
  },
  heroImage: {
    ...StyleSheet.absoluteFillObject,
  },
  heroGradient: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'space-between',
    padding: HORIZONTAL_MARGIN,
    paddingBottom: 24,
  },
  heroTopRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  heroBackBtn: {
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroCodePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(17, 17, 19, 0.8)',
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderWidth: 1,
    borderColor: ACCENT,
  },
  heroCodePillText: {
    fontSize: 10,
    color: ACCENT,
    fontWeight: '700',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
  },
  heroBottomContent: {
    gap: 4,
  },
  heroCodeBadge: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderColor: ACCENT,
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 3,
    marginBottom: 4,
  },
  heroCodeText: {
    fontSize: 11,
    color: ACCENT,
    fontWeight: '700',
    letterSpacing: 1,
  },
  heroTitle: {
    fontSize: 32,
    color: '#ffffff',
    letterSpacing: 1.5,
    lineHeight: 36,
  },

  contentArea: {
    backgroundColor: BODY_BG,
    marginTop: -20,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    paddingHorizontal: HORIZONTAL_MARGIN,
    paddingTop: 24,
    paddingBottom: 8,
  },

  sectionCard: {
    backgroundColor: CARD_BG,
    borderRadius: 12,
    borderCurve: 'continuous',
    borderWidth: 1,
    borderColor: CARD_BORDER,
    padding: 20,
    marginBottom: 16,
  },
  sectionCardLabel: {
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    marginBottom: 4,
  },
  sectionCardTitle: {
    fontSize: 22,
    letterSpacing: 1,
    marginBottom: 12,
  },
  placeholderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 8,
  },
  placeholderText: {
    fontSize: 12,
    color: TEXT_MUTED,
    flex: 1,
    fontStyle: 'italic',
  },

  leaderboardSection: {
    marginTop: 16,
  },
  sectionTitle: {
    fontSize: 22,
    color: '#1a1a1a',
    letterSpacing: 1.5,
    textTransform: 'uppercase',
  },
  sectionSubtitle: {
    fontSize: 11,
    color: TEXT_SECONDARY,
    letterSpacing: 0.5,
    lineHeight: 16,
    marginTop: 4,
    marginBottom: 16,
  },

  podiumRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'flex-end',
    gap: 6,
    paddingBottom: 8,
  },
  podiumItem: {
    alignItems: 'center',
    flex: 1,
    gap: 4,
  },
  podiumItemCenter: {
    flex: 1.4,
  },
  podiumCircle: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: CARD_BORDER,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: TEXT_MUTED,
  },
  podiumCircleGold: {
    borderColor: ACCENT,
  },
  podiumUsername: {
    fontSize: 10,
    color: TEXT_LIGHT,
    letterSpacing: 0.3,
    textTransform: 'uppercase',
    marginTop: 4,
    textAlign: 'center',
    maxWidth: 70,
  },
  podiumPoints: {
    fontSize: 12,
    color: ACCENT,
    fontWeight: '700',
    letterSpacing: 0.5,
    marginBottom: 4,
  },
  podiumBar: {
    width: '60%',
    borderRadius: 2,
    minHeight: 4,
  },
  podiumMoreRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    marginTop: 8,
  },
  podiumMoreText: {
    fontSize: 11,
    color: TEXT_MUTED,
    fontStyle: 'italic',
  },

  filterRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 16,
  },
  filterBtn: {
    flex: 1,
    height: 36,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filterBtnText: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },

  participantCard: {
    backgroundColor: CARD_BG,
    borderRadius: 12,
    borderCurve: 'continuous',
    borderWidth: 1,
    borderColor: CARD_BORDER,
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  participantLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  participantRank: {
    fontSize: 13,
    color: TEXT_SECONDARY,
    fontWeight: '700',
    width: 28,
  },
  participantAvatar: {
    width: 32,
    height: 32,
    borderRadius: 16,
    backgroundColor: CARD_BORDER,
    alignItems: 'center',
    justifyContent: 'center',
  },
  participantName: {
    fontSize: 13,
    color: TEXT_LIGHT,
    letterSpacing: 0.5,
    textTransform: 'uppercase',
    flex: 1,
  },
  participantStats: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  participantStatDivider: {
    width: 1,
    height: 24,
    backgroundColor: CARD_BORDER,
  },
  statValue: {
    fontSize: 13,
    color: TEXT_LIGHT,
    fontWeight: '700',
  },
  statLabel: {
    fontSize: 8,
    color: TEXT_SECONDARY,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginTop: 2,
  },
  leaderboardEmpty: {
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 24,
  },
  leaderboardEmptyText: {
    fontSize: 12,
    color: TEXT_MUTED,
    fontStyle: 'italic',
  },

  progresoCard: {
    backgroundColor: CARD_BG,
    borderRadius: 12,
    borderCurve: 'continuous',
    borderWidth: 1,
    borderColor: CARD_BORDER,
    padding: 20,
    marginTop: 16,
    marginBottom: 8,
  },
  progresoTitle: {
    fontSize: 14,
    color: ACCENT,
    letterSpacing: 1.5,
    textTransform: 'uppercase',
    marginBottom: 16,
  },
  inputRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 16,
  },
  inputGroup: {
    flex: 1,
  },
  inputLabel: {
    fontSize: 9,
    color: TEXT_SECONDARY,
    letterSpacing: 1,
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  input: {
    backgroundColor: BG_DARK,
    borderRadius: 10,
    height: 44,
    paddingHorizontal: 12,
    fontSize: 16,
    color: TEXT_LIGHT,
    fontWeight: '700',
    textAlign: 'center',
    borderBottomWidth: 2,
    borderBottomColor: ACCENT,
  },
  registerBtn: {
    height: 50,
    backgroundColor: ACCENT,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  registerBtnDisabled: {
    opacity: 0.4,
  },
  registerBtnText: {
    fontSize: 13,
    color: '#ffffff',
    fontWeight: '700',
    letterSpacing: 1.5,
    textTransform: 'uppercase',
  },

  recentCard: {
    backgroundColor: CARD_BG,
    borderRadius: 12,
    borderCurve: 'continuous',
    borderWidth: 1,
    borderColor: CARD_BORDER,
    padding: 20,
    marginTop: 12,
    marginBottom: 8,
  },
  recentTitle: {
    fontSize: 11,
    color: TEXT_SECONDARY,
    letterSpacing: 1.5,
    textTransform: 'uppercase',
    marginBottom: 14,
  },
  recentEmpty: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
  },
  recentEmptyText: {
    fontSize: 13,
    color: TEXT_MUTED,
    fontStyle: 'italic',
  },
  recentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: CARD_BORDER,
  },
  recentRowLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    flex: 1,
  },
  recentRowCenter: {
    flex: 1,
  },
  recentRowTitle: {
    fontSize: 13,
    color: TEXT_LIGHT,
    fontWeight: '700',
  },
  recentRowMeta: {
    fontSize: 11,
    color: TEXT_MUTED,
    marginTop: 2,
  },
  recentRowDate: {
    fontSize: 11,
    color: TEXT_MUTED,
    marginLeft: 8,
  },

  heroMenuBtn: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(17, 17, 19, 0.8)',
    borderWidth: 1,
    borderColor: CARD_BORDER,
    alignItems: 'center',
    justifyContent: 'center',
  },

  menuOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
    zIndex: 100,
  },
  menuSheet: {
    backgroundColor: CARD_BG,
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: 20,
    paddingBottom: 40,
  },
  menuOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
  },
  menuOptionText: {
    fontSize: 15,
    color: TEXT_LIGHT,
    fontWeight: '600',
  },
  menuDivider: {
    height: 1,
    backgroundColor: CARD_BORDER,
  },

  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  editModalContent: {
    backgroundColor: CARD_BG,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 360,
  },
  editModalTitle: {
    fontSize: 16,
    color: ACCENT,
    letterSpacing: 1.2,
    marginBottom: 16,
  },
  editModalInput: {
    backgroundColor: BG_DARK,
    borderRadius: 10,
    height: 48,
    paddingHorizontal: 14,
    fontSize: 16,
    color: TEXT_LIGHT,
    fontWeight: '700',
    borderBottomWidth: 2,
    borderBottomColor: ACCENT,
    marginBottom: 20,
  },
  editModalRow: {
    flexDirection: 'row',
    gap: 10,
  },
  editModalBtn: {
    flex: 1,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  editModalBtnText: {
    fontSize: 12,
    color: '#fff',
    fontWeight: '700',
    letterSpacing: 1,
  },
});
