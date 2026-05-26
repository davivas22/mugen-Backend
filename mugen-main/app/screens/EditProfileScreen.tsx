import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import { LinearGradient } from 'expo-linear-gradient';
import * as ImagePicker from 'expo-image-picker';
import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useColors } from '../context/ThemeContext';
import { getStorageUrl, userApi } from '../../services/api';
import { storage } from '../../services/storage';

export default function EditProfileScreen() {
  const navigation = useNavigation<any>();
  const { C, isDark } = useColors();

  const theme = {
    bg:               isDark ? '#000000' : '#f9f9f9',
    surface:          isDark ? '#131313' : '#ffffff',
    card:             isDark ? '#1f1f1f' : '#ffffff',
    border:           isDark ? '#2a2a2a' : '#e2e2e2',
    textPrimary:      isDark ? '#e2e2e2' : '#1a1c1c',
    textSecondary:    isDark ? '#e5bdc0' : '#5c3f42',
    textMuted:        isDark ? '#ac888a' : '#906e71',
    elevated:         isDark ? '#1b1b1b' : '#f3f3f4',
    primary:          '#FF2E63',
    primaryContainer: isDark ? '#ff4f72' : '#e51152',
  };

  const [form, setForm] = useState({
    fullName: '',
    username: '',
    bio:      '',
    weight:   '',
    height:   '',
  });
  const [avatarUri, setAvatarUri]   = useState<string | null>(null);
  const [token, setToken]           = useState('');
  const [loading, setLoading]       = useState(true);
  const [saving, setSaving]         = useState(false);
  const [focused, setFocused]       = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      const [t, userRaw] = await Promise.all([storage.get('token'), storage.get('user')]);
      if (t) setToken(t);

      if (userRaw) {
        try {
          const u = JSON.parse(userRaw);
          setForm({
            fullName: u.name      ?? '',
            username: u.username  ?? '',
            bio:      u.bio       ?? '',
            weight:   u.weight    ? String(u.weight) : '',
            height:   u.height    ? String(u.height) : '',
          });
          const savedAvatar = getStorageUrl(u.avatar);
          if (savedAvatar) setAvatarUri(savedAvatar);
        } catch (_) {}
      }

      // Refresh from API for latest data
      if (t) {
        try {
          const res = await userApi.me(t);
          const u   = res.data;
          setForm({
            fullName: u.name      ?? '',
            username: u.username  ?? '',
            bio:      u.bio       ?? '',
            weight:   u.weight    ? String(u.weight) : '',
            height:   u.height    ? String(u.height) : '',
          });
          const freshAvatar = getStorageUrl(u.avatar);
          if (freshAvatar) setAvatarUri(freshAvatar);
          await storage.set('user', JSON.stringify(u));
        } catch (_) {}
      }

      setLoading(false);
    })();
  }, []);

  const pickAvatar = async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert('Permiso requerido', 'Necesitamos acceso a tu galería para cambiar la foto.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.8,
    });

    if (!result.canceled && result.assets[0]) {
      setAvatarUri(result.assets[0].uri);
    }
  };

  const isLocalUri = (uri: string) =>
    uri.startsWith('file://') || uri.startsWith('content://') || uri.startsWith('/');

  const save = async () => {
    if (!form.fullName.trim()) {
      Alert.alert('Error', 'El nombre no puede estar vacío.');
      return;
    }

    setSaving(true);
    try {
      const data = new FormData();
      data.append('name',     form.fullName.trim());
      data.append('username', form.username.trim());
      data.append('bio',      form.bio.trim());
      if (form.weight) data.append('weight', form.weight);
      if (form.height) data.append('height', form.height);

      if (avatarUri && isLocalUri(avatarUri)) {
        // Force a clean filename with explicit jpeg type
        const rawName = avatarUri.split('/').pop() ?? 'avatar.jpg';
        const hasExt  = rawName.includes('.');
        const fileName = hasExt ? rawName : `${rawName}.jpg`;
        const ext      = hasExt ? (rawName.split('.').pop() ?? 'jpg').toLowerCase() : 'jpg';
        const mimeType = ext === 'jpg' || ext === 'jpeg' ? 'image/jpeg' : `image/${ext}`;

        console.log('[UPLOAD] avatar uri:', avatarUri);
        console.log('[UPLOAD] fileName:', fileName, 'mime:', mimeType);

        data.append('avatar', {
          uri:  Platform.OS === 'android' ? avatarUri : avatarUri.replace('file://', ''),
          name: fileName,
          type: mimeType,
        } as any);
      }

      const res  = await userApi.updateProfile(data, token);
      const user = res.data.user;
      await storage.set('user', JSON.stringify(user));

      Alert.alert('¡Listo!', 'Perfil actualizado correctamente.', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (err: any) {
      // Show real validation errors from Laravel (errors.avatar, errors.name, etc.)
      const errData = err?.response?.data;
      let msg = 'No se pudo guardar. Inténtalo de nuevo.';
      if (errData?.message) {
        msg = errData.message;
      } else if (errData?.errors) {
        msg = Object.values(errData.errors).flat().join('\n');
      }
      console.log('[UPLOAD] error:', JSON.stringify(errData));
      Alert.alert('Error al guardar', msg);
    } finally {
      setSaving(false);
    }
  };

  const update = (k: string, v: string) => setForm(p => ({ ...p, [k]: v }));

  const InputField = ({
    label, field, placeholder, extra = {} as any,
  }: { label: string; field: string; placeholder: string; extra?: any }) => (
    <View style={s.inputGroup}>
      <Text style={[s.label, { color: theme.textSecondary }]}>{label}</Text>
      <TextInput
        style={[
          s.input,
          { backgroundColor: theme.card, color: theme.textPrimary, borderColor: focused === field ? theme.primary : theme.border },
          extra.multiline && s.inputMulti,
        ]}
        value={(form as any)[field]}
        onChangeText={v => update(field, v)}
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        onFocus={() => setFocused(field)}
        onBlur={() => setFocused(null)}
        {...extra}
      />
    </View>
  );

  if (loading) {
    return (
      <View style={[s.root, { backgroundColor: theme.bg, justifyContent: 'center', alignItems: 'center' }]}>
        <ActivityIndicator color={theme.primary} size="large" />
      </View>
    );
  }

  return (
    <View style={[s.root, { backgroundColor: theme.bg }]}>
      <StatusBar barStyle={isDark ? 'light-content' : 'dark-content'} backgroundColor="transparent" translucent />

      <View style={[s.header, { backgroundColor: theme.bg, borderBottomColor: theme.border }]}>
        <SafeAreaView style={s.headerContainer}>
          <View style={s.headerInnerRow}>
            <TouchableOpacity
              onPress={() => navigation.goBack()}
              style={[s.closeBtn, { backgroundColor: theme.card, borderColor: theme.border }]}
              activeOpacity={0.7}
            >
              <MaterialCommunityIcons name="close" size={20} color={theme.textPrimary} />
            </TouchableOpacity>
            <Text style={[s.headerTitle, { color: theme.textPrimary }]}>Editar Perfil</Text>
            <View style={{ width: 40 }} />
          </View>
        </SafeAreaView>
      </View>

      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={s.keyboardView}>
        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={s.scroll}>

          {/* Avatar */}
          <View style={s.avatarSection}>
            <TouchableOpacity onPress={pickAvatar} activeOpacity={0.85} style={s.avatarTouchable}>
              <View style={[s.avatarRing, { borderColor: theme.primaryContainer, shadowColor: theme.primaryContainer }]}>
                {avatarUri ? (
                  <Image source={{ uri: avatarUri }} style={s.avatar} />
                ) : (
                  <View style={[s.avatarPlaceholder, { backgroundColor: theme.elevated }]}>
                    <MaterialCommunityIcons name="account" size={44} color={theme.primary} />
                  </View>
                )}
              </View>
              <View style={[s.cameraBtn, { backgroundColor: theme.primaryContainer, borderColor: theme.surface }]}>
                <MaterialCommunityIcons name="camera" size={16} color="#FFF" />
              </View>
            </TouchableOpacity>
            <TouchableOpacity onPress={pickAvatar} activeOpacity={0.7}>
              <Text style={[s.changePhoto, { color: theme.primaryContainer }]}>Cambiar foto</Text>
            </TouchableOpacity>
          </View>

          {/* Form */}
          <View style={s.form}>
            <InputField label="Nombre completo"    field="fullName" placeholder="Tu nombre" />
            <InputField label="Nombre de usuario"  field="username" placeholder="@usuario" extra={{ autoCapitalize: 'none' }} />
            <InputField
              label="Biografía"
              field="bio"
              placeholder="Cuéntanos algo..."
              extra={{ multiline: true, numberOfLines: 3, textAlignVertical: 'top' }}
            />
            <View style={s.metricsRow}>
              <View style={{ flex: 1 }}>
                <InputField label="Peso (kg)" field="weight" placeholder="00" extra={{ keyboardType: 'numeric' }} />
              </View>
              <View style={{ width: 14 }} />
              <View style={{ flex: 1 }}>
                <InputField label="Altura (cm)" field="height" placeholder="000" extra={{ keyboardType: 'numeric' }} />
              </View>
            </View>
          </View>

          <View style={{ height: 120 }} />
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Save button */}
      <View style={[s.footer, { backgroundColor: theme.bg }]}>
        <LinearGradient
          colors={isDark
            ? ['transparent', 'rgba(19,19,19,0.95)', '#131313']
            : ['transparent', 'rgba(249,249,249,0.95)', '#f9f9f9']}
          style={s.footerGradient}
        />
        <TouchableOpacity
          style={[s.saveBtn, { backgroundColor: theme.primaryContainer, shadowColor: theme.primaryContainer }, saving && { opacity: 0.6 }]}
          onPress={save}
          disabled={saving}
          activeOpacity={0.85}
        >
          {saving ? (
            <ActivityIndicator color="#FFF" />
          ) : (
            <View style={s.saveBtnContent}>
              <Text style={s.saveBtnText}>GUARDAR CAMBIOS</Text>
              <MaterialCommunityIcons name="check" size={18} color="#FFF" style={s.buttonCheckIcon} />
            </View>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );
}

const s = StyleSheet.create({
  root:           { flex: 1 },
  header:         { borderBottomWidth: 1, zIndex: 10 },
  headerContainer:{ width: '100%' },
  headerInnerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingVertical: 12 },
  closeBtn:       { width: 40, height: 40, borderRadius: 12, justifyContent: 'center', alignItems: 'center', borderWidth: 1 },
  headerTitle:    { fontFamily: Platform.OS === 'ios' ? 'System' : 'sans-serif-condensed', fontSize: 20, fontWeight: '900', letterSpacing: -0.5 },
  keyboardView:   { flex: 1 },
  scroll:         { paddingHorizontal: 20, paddingTop: 10 },
  avatarSection:  { alignItems: 'center', marginTop: 24, marginBottom: 32 },
  avatarTouchable:{ position: 'relative' },
  avatarRing:     { width: 108, height: 108, borderRadius: 54, borderWidth: 3, justifyContent: 'center', alignItems: 'center', padding: 2, shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.25, shadowRadius: 10, elevation: 8 },
  avatar:         { width: 96, height: 96, borderRadius: 48 },
  avatarPlaceholder: { width: 96, height: 96, borderRadius: 48, justifyContent: 'center', alignItems: 'center' },
  cameraBtn:      { position: 'absolute', bottom: 0, right: 0, width: 32, height: 32, borderRadius: 16, justifyContent: 'center', alignItems: 'center', borderWidth: 2, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.15, shadowRadius: 3, elevation: 4 },
  changePhoto:    { fontWeight: '800', fontSize: 13, marginTop: 14, textTransform: 'uppercase', letterSpacing: 0.8 },
  form:           { gap: 20 },
  inputGroup:     { gap: 8 },
  label:          { fontSize: 11, fontWeight: '800', letterSpacing: 1, textTransform: 'uppercase', marginLeft: 4 },
  input:          { height: 52, borderRadius: 16, paddingHorizontal: 16, fontSize: 15, fontWeight: '600', borderWidth: 1.5 },
  inputMulti:     { height: 96, paddingTop: 14 },
  metricsRow:     { flexDirection: 'row' },
  footer:         { position: 'absolute', bottom: 0, left: 0, right: 0, paddingHorizontal: 20, paddingTop: 24, paddingBottom: Platform.OS === 'ios' ? 34 : 20 },
  footerGradient: { position: 'absolute', top: -24, left: 0, right: 0, height: 24 },
  saveBtn:        { height: 56, borderRadius: 16, justifyContent: 'center', alignItems: 'center', shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.3, shadowRadius: 12, elevation: 8 },
  saveBtnContent: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center' },
  saveBtnText:    { color: '#FFF', fontSize: 14, fontWeight: '900', letterSpacing: 1.5 },
  buttonCheckIcon:{ marginLeft: 6 },
});
